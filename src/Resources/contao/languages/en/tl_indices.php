<?php

use Alnv\ProSearchIndexerContaoAdapterBundle\Helpers\States;

// Legends
$GLOBALS['TL_LANG']['tl_indices']['settings_legend'] = 'Settings';
$GLOBALS['TL_LANG']['tl_indices']['types_legend'] = 'Categories';
$GLOBALS['TL_LANG']['tl_indices']['page_legend'] = 'Page';
$GLOBALS['TL_LANG']['tl_indices']['meta_legend'] = 'Metadata';
$GLOBALS['TL_LANG']['tl_indices']['document_legend'] = 'Indexed content';
$GLOBALS['TL_LANG']['tl_indices']['protected_legend'] = 'Access protection';

// Fields
$GLOBALS['TL_LANG']['tl_indices']['id'] = ['ID', 'Unique ID of the record. It is also the document ID in the search index.'];
$GLOBALS['TL_LANG']['tl_indices']['tstamp'] = ['Revision date', 'Date and time of the last change.'];
$GLOBALS['TL_LANG']['tl_indices']['settings'] = ['Search settings', 'Defines how the document is treated the next time it is indexed.'];
$GLOBALS['TL_LANG']['tl_indices']['state'] = ['Status', '"Active": the document can be found. "Deleted": it is removed from the search index on the next transfer run.'];
$GLOBALS['TL_LANG']['tl_indices']['types'] = ['Categories', 'Categories of the document. The search filters by them (module setting "Categories") and groups the results.'];
$GLOBALS['TL_LANG']['tl_indices']['domain'] = ['Domain', 'Domain the document is available on. It determines the license key.'];
$GLOBALS['TL_LANG']['tl_indices']['url'] = ['URL', 'Address of the indexed document. Every URL exists only once.'];
$GLOBALS['TL_LANG']['tl_indices']['url_hash'] = ['URL hash', 'SHA-1 of the URL (unique key, set automatically).'];
$GLOBALS['TL_LANG']['tl_indices']['origin_url'] = ['Original URL', 'Address the document was found at, e.g. before a redirect.'];
$GLOBALS['TL_LANG']['tl_indices']['language'] = ['Language', 'Language of the document (e.g. "en"). The search only returns documents in the language of the search module or page.'];
$GLOBALS['TL_LANG']['tl_indices']['pageId'] = ['Page', 'Contao page the document was indexed on.'];
$GLOBALS['TL_LANG']['tl_indices']['rootId'] = ['Website root', 'ID of the root page. It determines the search index the document is stored in.'];
$GLOBALS['TL_LANG']['tl_indices']['doc_type'] = ['Document type', '"page" for pages, "file" or the file type for documents such as PDFs.'];
$GLOBALS['TL_LANG']['tl_indices']['title'] = ['Title', 'Headline of the search result. Only editable if "Do not update title, description and image" or "Prevent re-indexing" is set.'];
$GLOBALS['TL_LANG']['tl_indices']['description'] = ['Description', 'Teaser of the search result. Only editable if "Do not update title, description and image" or "Prevent re-indexing" is set.'];
$GLOBALS['TL_LANG']['tl_indices']['images'] = ['Preview images', 'Images shown in the search result.'];
$GLOBALS['TL_LANG']['tl_indices']['text'] = ['Text', 'Full text of the document.'];
$GLOBALS['TL_LANG']['tl_indices']['strong'] = ['Emphasized terms', 'Bold terms (<strong>/<b>). They are weighted higher in the search.'];
$GLOBALS['TL_LANG']['tl_indices']['headings'] = ['Headings', 'Headings h1–h6 of the document. They are weighted higher in the search.'];
$GLOBALS['TL_LANG']['tl_indices']['protected'] = ['Protect index', 'Only show the document to logged-in members of the selected groups.'];
$GLOBALS['TL_LANG']['tl_indices']['groups'] = ['Member groups', 'Only members of these groups find the document.'];
$GLOBALS['TL_LANG']['tl_indices']['last_indexed'] = ['Last indexed', 'Date and time the document was last captured.'];
$GLOBALS['TL_LANG']['tl_indices']['checksum'] = ['Checksum', 'Checksum of metadata and content. If it does not change, the document is not transferred again.'];
$GLOBALS['TL_LANG']['tl_indices']['content_hash'] = ['Content hash', 'SHA-256 of the text. If it changes, the chunks are recalculated.'];
$GLOBALS['TL_LANG']['tl_indices']['source_hash'] = ['Source hash', 'Hash of the source file (e.g. PDF). An unchanged file is not parsed again.'];
$GLOBALS['TL_LANG']['tl_indices']['chunk_count'] = ['Chunks', 'Number of text sections (max. 600 tokens) for the semantic search.'];
$GLOBALS['TL_LANG']['tl_indices']['revision'] = ['Revision', 'Increased with every change of the content.'];
$GLOBALS['TL_LANG']['tl_indices']['synced_revision'] = ['Transferred revision', 'Revision last transferred to the search index.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_status'] = ['Search index', 'Status in the search index: ✓ = transferred, "pending" = transferred on the next run (usually within a minute), "failed" = see "Last transfer error".'];
$GLOBALS['TL_LANG']['tl_indices']['sync_attempts'] = ['Transfer attempts', 'Number of failed attempts since the last successful transfer.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_error'] = ['Last transfer error', 'Error message of the last failed attempt.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_next_at'] = ['Next attempt', 'Earliest time of the next transfer.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_claim'] = ['Transfer token', 'ID of the run that is currently transferring the record.'];
$GLOBALS['TL_LANG']['tl_indices']['sync_claimed_until'] = ['Reserved until', 'The record is reserved for the running transfer until this time.'];
$GLOBALS['TL_LANG']['tl_indices']['synced_index'] = ['Search index', 'Name of the index the document was last transferred to.'];

// Virtual fields (indexed content, see IndicesListener)
$GLOBALS['TL_LANG']['tl_indices']['indices_text'] = ['Text', 'Full text (page or PDF). Only editable with "Prevent re-indexing".'];
$GLOBALS['TL_LANG']['tl_indices']['indices_strong'] = ['Emphasized terms', 'Bold terms. Only editable with "Prevent re-indexing".'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h1'] = ['Headings H1', 'Only editable with "Prevent re-indexing".'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h2'] = ['Headings H2', 'Only editable with "Prevent re-indexing".'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h3'] = ['Headings H3', 'Only editable with "Prevent re-indexing".'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h4'] = ['Headings H4', 'Only editable with "Prevent re-indexing".'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h5'] = ['Headings H5', 'Only editable with "Prevent re-indexing".'];
$GLOBALS['TL_LANG']['tl_indices']['indices_h6'] = ['Headings H6', 'Only editable with "Prevent re-indexing".'];
$GLOBALS['TL_LANG']['tl_indices']['indices_document'] = ['PDF text', 'Text of the PDF document.'];

// Operations
$GLOBALS['TL_LANG']['tl_indices']['edit'] = ['Edit', 'Edit record ID %s'];
$GLOBALS['TL_LANG']['tl_indices']['delete'] = ['Delete', 'Delete record ID %s'];
$GLOBALS['TL_LANG']['tl_indices']['show'] = ['Details', 'Show the details of record ID %s'];
$GLOBALS['TL_LANG']['tl_indices']['reindex'] = ['Re-index', 'Re-index page ID %s'];

// References
$GLOBALS['TL_LANG']['tl_indices']['settings_options'] = [
    'preventIndexMetadata' => 'Do not update title, description and image',
    'preventIndex' => 'Prevent re-indexing (maintain the content manually)',
    'doNotShow' => 'Hide in the search'
];
$GLOBALS['TL_LANG']['tl_indices']['states'][States::ACTIVE] = 'Active';
$GLOBALS['TL_LANG']['tl_indices']['states'][States::DELETE] = 'Deleted';
$GLOBALS['TL_LANG']['tl_indices']['sync_states'] = [
    'pending' => 'pending',
    'done' => 'transferred',
    'failed' => 'failed'
];
