<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\EventListener\DataContainer;

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndexDocument;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\IndicesRepository;
use Alnv\ProSearchIndexerContaoAdapterBundle\Indexing\SyncStatus;
use Alnv\ProSearchIndexerContaoAdapterBundle\Search\IndexingService;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;

class IndicesListener
{
    private const string PREFIX = 'indices_';

    public function __construct(
        private readonly Connection        $connection,
        private readonly IndicesRepository $repository,
        private readonly IndexingService   $indexingService,
    )
    {
    }

    public function onLoad(DataContainer $dc): void
    {
        if ('edit' !== Input::get('act') || !$dc->id) {
            return;
        }

        $row = $this->connection->fetchAssociative('SELECT settings, text, strong, headings FROM ' . IndicesRepository::TABLE . ' WHERE id=?', [(int)$dc->id]);

        if (!$row) {
            return;
        }

        $settings = StringUtil::deserialize(Input::post('settings') ?: $row['settings'], true);
        $manual = \in_array('preventIndex', $settings, true);

        if (!\in_array('preventIndexMetadata', $settings, true) && !$manual) {
            $GLOBALS['TL_DCA']['tl_indices']['fields']['title']['eval']['readonly'] = true;
            $GLOBALS['TL_DCA']['tl_indices']['fields']['description']['eval']['readonly'] = true;
            PaletteManipulator::create()->removeField('images')->applyToPalette('default', 'tl_indices');
        }

        $isSaving = Input::post('FORM_SUBMIT') === 'tl_indices';
        $headings = StringUtil::deserialize($row['headings'], true) + IndexDocument::emptyHeadings();
        $values = ['text' => (string)$row['text'], 'strong' => StringUtil::deserialize($row['strong'], true)] + $headings;
        $palette = PaletteManipulator::create()->addLegend('document_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE);

        foreach ($values as $name => $value) {
            $field = self::PREFIX . $name;

            $GLOBALS['TL_DCA']['tl_indices']['fields'][$field] = [
                'label' => &$GLOBALS['TL_LANG']['tl_indices'][$field],
                'inputType' => 'text' === $name ? 'textarea' : 'listWizard',
                'eval' => ['doNotSaveEmpty' => true, 'alwaysSave' => true, 'decodeEntities' => true, 'doNotCopy' => true, 'readonly' => !$manual, 'tl_class' => 'clr'],
                // virtual fields: the value comes from the record, the input is handled in onSubmit()
                'load_callback' => [static fn() => $value],
                'save_callback' => [static fn() => ''],
            ];

            if ($isSaving) {
                unset($GLOBALS['TL_DCA']['tl_indices']['fields'][$field]['load_callback']);
            }

            $palette->addField($field, 'document_legend', PaletteManipulator::POSITION_APPEND);
        }

        $palette->applyToPalette('default', 'tl_indices');
    }

    public function onSubmit(DataContainer $dc): void
    {
        $id = (int)$dc->id;
        $row = $this->connection->fetchAssociative('SELECT state, settings FROM ' . IndicesRepository::TABLE . ' WHERE id=?', [$id]);

        if (!$row) {
            return;
        }

        // the URL may have been edited (unique, checked by the DCA)
        $this->connection->executeStatement("UPDATE " . IndicesRepository::TABLE . " SET url_hash=SHA1(url) WHERE id=? AND url IS NOT NULL AND url<>''", [$id]);

        if (States::DELETE === $row['state']) {
            $this->repository->markDeleted([$id]);
            Message::addInfo('Der Eintrag wird beim nächsten Lauf der Übertragung aus dem Suchindex entfernt.');

            return;
        }

        if (\in_array('preventIndex', StringUtil::deserialize($row['settings'], true), true) && null !== Input::post(self::PREFIX . 'text')) {
            $headings = [];

            foreach (array_keys(IndexDocument::emptyHeadings()) as $level) {
                $headings[$level] = self::listValue(Input::post(self::PREFIX . $level));
            }

            $this->repository->updateContent(
                $id,
                (string)Input::postUnsafeRaw(self::PREFIX . 'text'),
                self::listValue(Input::post(self::PREFIX . 'strong')),
                $headings,
            );
        }

        $this->repository->requeue([$id]);
        Message::addInfo('Die Änderungen werden beim nächsten Lauf der Übertragung (spätestens in einer Minute) in den Suchindex übernommen.');
    }

    public function onDelete(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        if ($this->indexingService->deleteIndex((int)$dc->id)) {
            return;
        }

        $status = $this->connection->fetchOne('SELECT sync_error FROM ' . IndicesRepository::TABLE . ' WHERE id=?', [(int)$dc->id]);
        Message::addError(\sprintf('Der Eintrag %d konnte noch nicht aus dem Suchindex entfernt werden%s. Er ist zum Löschen vorgemerkt und wird automatisch entfernt, sobald der ProSearch-Proxy erreichbar ist.', (int)$dc->id, $status ? ' (' . $status . ')' : ''));

        Controller::redirect(System::getReferer());
    }

    /**
     * List view, column "Suchindex": a check mark for transferred records, pending records in gray, failed
     * transfers in red with the error as tooltip.
     *
     * @param array<string, mixed> $row
     * @param list<string>         $args column values in the order of list.label.fields
     *
     * @return list<string>
     */
    public function listLabel(array $row, string $label, DataContainer $dc, array $args): array
    {
        $fields = $GLOBALS['TL_DCA']['tl_indices']['list']['label']['fields'] ?? [];
        $column = array_search('sync_status', $fields, true);

        if (false === $column) {
            return $args;
        }

        $status = (string)($row['sync_status'] ?? '');
        $text = StringUtil::specialchars((string)($GLOBALS['TL_LANG']['tl_indices']['sync_states'][$status] ?? self::syncLabel($status)));

        $args[$column] = match ($status) {
            // check mark; the text stays available for screen readers and as tooltip
            SyncStatus::DONE => '<span class="tl_green" title="' . $text . '" aria-hidden="true">✓</span><span class="invisible">' . $text . '</span>',
            SyncStatus::FAILED => \sprintf('<span class="tl_red"%s>%s</span>', '' !== (string)($row['sync_error'] ?? '') ? ' title="' . StringUtil::specialchars((string)$row['sync_error']) . '"' : '', $text),
            default => '<span class="tl_gray">' . $text . '</span>',
        };

        return $args;
    }

    public static function syncLabel(string $status): string
    {
        return match ($status) {
            SyncStatus::DONE => 'übertragen',
            SyncStatus::FAILED => 'fehlgeschlagen',
            default => 'ausstehend',
        };
    }

    private static function listValue(mixed $value): array
    {
        $values = \is_array($value) ? $value : StringUtil::deserialize($value, true);

        return array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $values), static fn(string $v): bool => '' !== $v));
    }
}
