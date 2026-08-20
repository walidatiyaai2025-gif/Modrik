<?php

namespace App\Services;

use App\Exceptions\ContentValidationException;
use JsonException;
use ZipArchive;

final class ContentPackArchiveValidator
{
    /**
     * @return array{
     *   manifest: array<string, mixed>,
     *   content_pack: array<string, mixed>,
     *   files: list<array{path: string, media_type: string, sha256: string, bytes: int}>,
     *   imported_record_count: int
     * }
     *
     * @throws ContentValidationException
     */
    public function validate(string $archivePath): array
    {
        $zip = new ZipArchive;
        $opened = $zip->open($archivePath, ZipArchive::RDONLY);
        if ($opened !== true) {
            $this->fail('CONTENT_ARCHIVE_UNREADABLE', 'The upload is not a readable ZIP archive.', '/archive');
        }

        $manifest = null;
        try {
            $entries = $this->inspectEntries($zip);
            if (! array_key_exists('manifest.json', $entries)) {
                $this->fail('CONTENT_MANIFEST_MISSING', 'The archive must contain manifest.json.', '/archive');
            }
            $manifestIndex = $entries['manifest.json']['index'];
            if ($entries['manifest.json']['bytes'] > (int) config('modrik.content_import.maximum_manifest_bytes')) {
                $this->fail('CONTENT_MANIFEST_TOO_LARGE', 'manifest.json exceeds the configured limit.', '/manifest.json');
            }
            $manifestJson = $zip->getFromIndex($manifestIndex);
            if (! is_string($manifestJson)) {
                $this->fail('CONTENT_MANIFEST_UNREADABLE', 'manifest.json could not be read.', '/manifest.json');
            }
            $manifest = $this->decodeObject($manifestJson, '/manifest.json');
            $files = $this->validateManifest($manifest, $entries);

            foreach ($files as $file) {
                $index = $entries[$file['path']]['index'];
                $contents = $zip->getFromIndex($index);
                if (! is_string($contents)) {
                    $this->fail('CONTENT_FILE_UNREADABLE', 'A declared content file could not be read.', '/files/'.$file['path'], $manifest);
                }
                if (! hash_equals($file['sha256'], hash('sha256', $contents))) {
                    $this->fail('CONTENT_FILE_HASH_MISMATCH', 'A declared content file does not match its SHA-256 digest.', '/files/'.$file['path'], $manifest);
                }
            }

            $contentEntry = $entries['content-pack.json'] ?? null;
            if ($contentEntry === null) {
                $this->fail('CONTENT_PACK_MISSING', 'The archive must declare content-pack.json.', '/files', $manifest);
            }
            $contentJson = $zip->getFromIndex($contentEntry['index']);
            if (! is_string($contentJson)) {
                $this->fail('CONTENT_PACK_UNREADABLE', 'content-pack.json could not be read.', '/content-pack.json', $manifest);
            }
            $contentPack = $this->decodeObject($contentJson, '/content-pack.json', $manifest);
            $recordCount = $this->validateContentPack($contentPack, $manifest);

            return [
                'manifest' => $manifest,
                'content_pack' => $contentPack,
                'files' => $files,
                'imported_record_count' => $recordCount,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, array{index: int, bytes: int, compressed_bytes: int}>
     */
    private function inspectEntries(ZipArchive $zip): array
    {
        $maximumFiles = (int) config('modrik.content_import.maximum_file_count');
        if ($zip->numFiles < 1 || $zip->numFiles > $maximumFiles + 1) {
            $this->fail('CONTENT_ARCHIVE_FILE_COUNT_EXCEEDED', 'The archive contains too many files.', '/archive');
        }

        $entries = [];
        $totalBytes = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if (! is_array($stat) || ! is_string($stat['name'] ?? null)) {
                $this->fail('CONTENT_ARCHIVE_ENTRY_INVALID', 'An archive entry could not be inspected.', '/archive');
            }
            $name = $stat['name'];
            $this->validatePath($name);
            if (array_key_exists($name, $entries)) {
                $this->fail('CONTENT_ARCHIVE_DUPLICATE_PATH', 'Archive paths must be unique.', '/archive/'.$name);
            }

            $bytes = (int) ($stat['size'] ?? -1);
            $compressedBytes = (int) ($stat['comp_size'] ?? -1);
            if ($bytes < 0 || $compressedBytes < 0 || $bytes > (int) config('modrik.content_import.maximum_entry_bytes')) {
                $this->fail('CONTENT_ARCHIVE_ENTRY_SIZE_EXCEEDED', 'An archive entry exceeds the configured size limit.', '/archive/'.$name);
            }
            $ratio = $compressedBytes === 0 ? ($bytes === 0 ? 1.0 : INF) : $bytes / $compressedBytes;
            if ($ratio > (int) config('modrik.content_import.maximum_compression_ratio')) {
                $this->fail('CONTENT_ARCHIVE_RATIO_EXCEEDED', 'An archive entry exceeds the configured compression-ratio limit.', '/archive/'.$name);
            }

            $operatingSystem = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)
                && $operatingSystem === ZipArchive::OPSYS_UNIX
                && (($attributes >> 16) & 0xF000) === 0xA000) {
                $this->fail('CONTENT_ARCHIVE_SYMLINK_REJECTED', 'Symbolic links are not allowed in returned archives.', '/archive/'.$name);
            }

            $totalBytes += $bytes;
            if ($totalBytes > (int) config('modrik.content_import.maximum_archive_bytes')) {
                $this->fail('CONTENT_ARCHIVE_SIZE_EXCEEDED', 'The archive exceeds the configured uncompressed size limit.', '/archive');
            }
            $entries[$name] = ['index' => $index, 'bytes' => $bytes, 'compressed_bytes' => $compressedBytes];
        }

        return $entries;
    }

    private function validatePath(string $path): void
    {
        $segments = explode('/', $path);
        if ($path === '' || strlen($path) > 240 || str_contains($path, "\0") || str_contains($path, '\\')
            || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1
            || preg_match('#^[A-Za-z0-9._/-]+$#', $path) !== 1 || str_ends_with($path, '/')
            || in_array('..', $segments, true) || in_array('', $segments, true)) {
            $this->fail('CONTENT_ARCHIVE_PATH_UNSAFE', 'Archive paths must be normalized relative file paths.', '/archive');
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, array{index: int, bytes: int, compressed_bytes: int}>  $entries
     * @return list<array{path: string, media_type: string, sha256: string, bytes: int}>
     */
    private function validateManifest(array $manifest, array $entries): array
    {
        $this->exactKeys($manifest, [
            'schema_version', 'preparation_request_id', 'settings_hash', 'pack_id', 'created_at',
            'generator', 'provenance', 'archive_limits', 'files',
        ], '/manifest.json', $manifest);
        if (($manifest['schema_version'] ?? null) !== '1.0.0') {
            $this->fail('CONTENT_SCHEMA_VERSION_UNSUPPORTED', 'Only Content Pack schema 1.0.0 is supported.', '/schema_version', $manifest);
        }
        foreach (['preparation_request_id', 'pack_id'] as $field) {
            if (! is_string($manifest[$field] ?? null) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $manifest[$field]) !== 1) {
                $this->fail('CONTENT_MANIFEST_INVALID', "{$field} must be a ULID.", '/'.$field, $manifest);
            }
        }
        if (! is_string($manifest['settings_hash'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $manifest['settings_hash']) !== 1) {
            $this->fail('CONTENT_MANIFEST_INVALID', 'settings_hash must be a lowercase SHA-256 digest.', '/settings_hash', $manifest);
        }

        $this->validateManifestMetadata($manifest);

        $provenance = $manifest['provenance'] ?? null;
        if (! is_array($provenance) || ($provenance['contains_student_pii'] ?? null) !== false
            || ! in_array($provenance['rights_status'] ?? null, ['synthetic_fixture', 'owner_created', 'licensed', 'public_domain', 'pending_review'], true)) {
            $this->fail('CONTENT_PROVENANCE_INVALID', 'Provenance must declare no student PII and a supported rights state.', '/provenance', $manifest);
        }
        $limits = $manifest['archive_limits'] ?? null;
        $declaredFiles = $manifest['files'] ?? null;
        if (! is_array($limits) || ! is_array($declaredFiles) || ! array_is_list($declaredFiles)) {
            $this->fail('CONTENT_MANIFEST_INVALID', 'Archive limits and files must be present.', '/files', $manifest);
        }
        if (count($declaredFiles) < 1 || count($declaredFiles) > (int) config('modrik.content_import.maximum_file_count')) {
            $this->fail('CONTENT_ARCHIVE_FILE_COUNT_EXCEEDED', 'The declared file count is outside configured limits.', '/files', $manifest);
        }

        $allowedMedia = ['application/json', 'image/png', 'image/jpeg', 'image/svg+xml', 'audio/mpeg', 'video/mp4'];
        $files = [];
        $paths = [];
        $totalBytes = 0;
        foreach ($declaredFiles as $position => $file) {
            if (! is_array($file)) {
                $this->fail('CONTENT_MANIFEST_INVALID', 'Each file declaration must be an object.', '/files/'.$position, $manifest);
            }
            $this->exactKeys($file, ['path', 'media_type', 'sha256', 'bytes'], '/files/'.$position, $manifest);
            $path = $file['path'] ?? null;
            $mediaType = $file['media_type'] ?? null;
            $sha256 = $file['sha256'] ?? null;
            $bytes = $file['bytes'] ?? null;
            if (! is_string($path) || ! is_string($mediaType) || ! is_string($sha256) || ! is_int($bytes)) {
                $this->fail('CONTENT_MANIFEST_INVALID', 'File declarations have invalid field types.', '/files/'.$position, $manifest);
            }
            $this->validatePath($path);
            if (isset($paths[$path])) {
                $this->fail('CONTENT_ARCHIVE_DUPLICATE_PATH', 'Declared file paths must be unique.', '/files/'.$position.'/path', $manifest);
            }
            if (! in_array($mediaType, $allowedMedia, true) || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1 || $bytes < 1) {
                $this->fail('CONTENT_MANIFEST_INVALID', 'A declared file has invalid media, hash, or size.', '/files/'.$position, $manifest);
            }
            if (! isset($entries[$path]) || $entries[$path]['bytes'] !== $bytes) {
                $this->fail('CONTENT_FILE_SIZE_MISMATCH', 'A declared file is absent or has a different byte count.', '/files/'.$position, $manifest);
            }
            $paths[$path] = true;
            $totalBytes += $bytes;
            $files[] = ['path' => $path, 'media_type' => $mediaType, 'sha256' => $sha256, 'bytes' => $bytes];
        }

        $actualContentPaths = array_values(array_filter(array_keys($entries), static fn (string $path): bool => $path !== 'manifest.json'));
        sort($actualContentPaths, SORT_STRING);
        $declaredPaths = array_keys($paths);
        sort($declaredPaths, SORT_STRING);
        if ($actualContentPaths !== $declaredPaths) {
            $this->fail('CONTENT_ARCHIVE_UNDECLARED_FILE', 'Every non-manifest archive file must be declared exactly once.', '/files', $manifest);
        }
        if (($limits['declared_file_count'] ?? null) !== count($files)
            || ($limits['declared_uncompressed_bytes'] ?? null) !== $totalBytes) {
            $this->fail('CONTENT_ARCHIVE_LIMIT_MISMATCH', 'Declared archive totals do not match the file manifest.', '/archive_limits', $manifest);
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $pack
     * @param  array<string, mixed>  $manifest
     */
    private function validateContentPack(array $pack, array $manifest): int
    {
        $this->exactKeys($pack, ['pack_id', 'schema_version', 'academic_scope', 'curriculum_nodes', 'lessons', 'questions', 'quizzes'], '/content-pack.json', $manifest);
        if (($pack['schema_version'] ?? null) !== '1.0.0' || ! $this->isUlid($pack['pack_id'] ?? null)
            || ($pack['pack_id'] ?? null) !== ($manifest['pack_id'] ?? null)) {
            $this->fail('CONTENT_PACK_BINDING_MISMATCH', 'The Content Pack ID/schema does not match its manifest.', '/content-pack.json', $manifest);
        }
        $this->validateAcademicScope($pack['academic_scope'] ?? null, '/academic_scope', $manifest);
        foreach (['curriculum_nodes', 'lessons', 'questions', 'quizzes'] as $field) {
            if (! is_array($pack[$field] ?? null) || ! array_is_list($pack[$field])) {
                $this->fail('CONTENT_PACK_SCHEMA_INVALID', "{$field} must be an object or array as defined by schema v1.", '/'.$field, $manifest);
            }
        }

        $nodes = $pack['curriculum_nodes'];
        if ($nodes === []) {
            $this->schemaFail('Content Pack v1 requires at least one curriculum node.', '/curriculum_nodes', $manifest);
        }
        $nodeReferences = [];
        foreach ($nodes as $position => $node) {
            $pointer = '/curriculum_nodes/'.$position;
            if (! is_array($node) || array_is_list($node)) {
                $this->schemaFail('Each curriculum node must be an object.', $pointer, $manifest);
            }
            $this->objectKeys($node, ['reference', 'type', 'title'], ['reference', 'parent_reference', 'type', 'title'], $pointer, $manifest);
            if (! $this->isReference($node['reference'] ?? null)
                || ! in_array($node['type'] ?? null, ['subject', 'unit', 'topic'], true)) {
                $this->schemaFail('Curriculum node fields do not match Content Pack schema v1.', $pointer, $manifest);
            }
            $this->validateLocalizedText($node['title'] ?? null, $pointer.'/title', $manifest);
            if (isset($nodeReferences[$node['reference']])) {
                $this->fail('CONTENT_REFERENCE_DUPLICATE', 'Curriculum node references must be unique.', '/curriculum_nodes/'.$position.'/reference', $manifest);
            }
            $nodeReferences[$node['reference']] = true;
        }
        foreach ($nodes as $position => $node) {
            $parent = $node['parent_reference'] ?? null;
            if ($parent !== null && (! is_string($parent) || ! isset($nodeReferences[$parent]))) {
                $this->fail('CONTENT_REFERENCE_INVALID', 'A curriculum parent reference does not exist.', '/curriculum_nodes/'.$position.'/parent_reference', $manifest);
            }
        }

        $questionIds = [];
        foreach ($pack['questions'] as $position => $question) {
            if (! is_array($question) || array_is_list($question) || ! $this->isUlid($question['id'] ?? null)
                || ! is_string($question['curriculum_node_reference'] ?? null)
                || ! isset($nodeReferences[$question['curriculum_node_reference']])) {
                $this->fail('CONTENT_REFERENCE_INVALID', 'A question reference is invalid.', '/questions/'.$position, $manifest);
            }
            if (isset($questionIds[$question['id']])) {
                $this->fail('CONTENT_REFERENCE_DUPLICATE', 'Question IDs must be unique.', '/questions/'.$position.'/id', $manifest);
            }
            $questionIds[$question['id']] = true;
            $this->validateQuestion($question, $position, $manifest);
        }

        $lessonIds = [];
        foreach ($pack['lessons'] as $position => $lesson) {
            $pointer = '/lessons/'.$position;
            if (! is_array($lesson) || array_is_list($lesson) || ! $this->isUlid($lesson['id'] ?? null)
                || ! isset($nodeReferences[$lesson['curriculum_node_reference'] ?? ''])) {
                $this->fail('CONTENT_REFERENCE_INVALID', 'A lesson reference is invalid.', '/lessons/'.$position, $manifest);
            }
            $this->objectKeys(
                $lesson,
                ['id', 'curriculum_node_reference', 'slug', 'content_version', 'title', 'blocks'],
                ['id', 'curriculum_node_reference', 'slug', 'content_version', 'title', 'blocks'],
                $pointer,
                $manifest,
            );
            if (! is_string($lesson['slug'] ?? null) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $lesson['slug']) !== 1
                || strlen($lesson['slug']) > 120 || ! is_int($lesson['content_version'] ?? null) || $lesson['content_version'] < 1
                || ! is_array($lesson['blocks'] ?? null) || ! array_is_list($lesson['blocks'])
                || $lesson['blocks'] === [] || count($lesson['blocks']) > 500) {
                $this->schemaFail('Lesson fields do not match Content Pack schema v1.', $pointer, $manifest);
            }
            $this->validateLocalizedText($lesson['title'] ?? null, $pointer.'/title', $manifest);
            if (isset($lessonIds[$lesson['id']])) {
                $this->fail('CONTENT_REFERENCE_DUPLICATE', 'Lesson IDs must be unique.', '/lessons/'.$position.'/id', $manifest);
            }
            $lessonIds[$lesson['id']] = true;
            $positions = [];
            foreach ($lesson['blocks'] as $blockPosition => $block) {
                $blockPointer = $pointer.'/blocks/'.$blockPosition;
                if (! is_array($block) || array_is_list($block)) {
                    $this->schemaFail('Lesson blocks must be objects.', $blockPointer, $manifest);
                }
                $this->objectKeys(
                    $block,
                    ['id', 'position', 'type', 'content'],
                    ['id', 'position', 'type', 'content', 'media_reference'],
                    $blockPointer,
                    $manifest,
                );
                if (! $this->isUlid($block['id'] ?? null) || ! is_int($block['position'] ?? null) || $block['position'] < 1
                    || ! in_array($block['type'] ?? null, ['heading', 'rich_text', 'callout', 'worked_example', 'media_reference'], true)
                    || (isset($block['media_reference']) && (! is_string($block['media_reference'])
                        || preg_match('#^media/[A-Za-z0-9._/-]+$#', $block['media_reference']) !== 1
                        || strlen($block['media_reference']) > 240))) {
                    $this->schemaFail('Lesson block fields do not match Content Pack schema v1.', $blockPointer, $manifest);
                }
                $this->validateLocalizedText($block['content'] ?? null, $blockPointer.'/content', $manifest);
                if (isset($positions[$block['position']])) {
                    $this->fail('CONTENT_BLOCK_ORDER_INVALID', 'Lesson block positions must be unique ordered integers.', '/lessons/'.$position.'/blocks', $manifest);
                }
                $positions[$block['position']] = true;
            }
            $ordered = array_keys($positions);
            $sorted = $ordered;
            sort($sorted, SORT_NUMERIC);
            if ($ordered !== $sorted) {
                $this->fail('CONTENT_BLOCK_ORDER_INVALID', 'Lesson blocks must be ordered by position.', '/lessons/'.$position.'/blocks', $manifest);
            }
        }

        $quizIds = [];
        foreach ($pack['quizzes'] as $position => $quiz) {
            $pointer = '/quizzes/'.$position;
            if (! is_array($quiz) || array_is_list($quiz) || ! $this->isUlid($quiz['id'] ?? null)
                || ! isset($nodeReferences[$quiz['curriculum_node_reference'] ?? ''])) {
                $this->fail('CONTENT_REFERENCE_INVALID', 'A quiz reference is invalid.', '/quizzes/'.$position, $manifest);
            }
            $this->exactKeys($quiz, ['id', 'curriculum_node_reference', 'kind', 'blueprint_version', 'title', 'question_ids'], $pointer, $manifest);
            if (! in_array($quiz['kind'] ?? null, ['practice', 'quiz', 'mock_exam'], true)
                || ! is_int($quiz['blueprint_version'] ?? null) || $quiz['blueprint_version'] < 1) {
                $this->schemaFail('Quiz fields do not match Content Pack schema v1.', $pointer, $manifest);
            }
            $this->validateLocalizedText($quiz['title'] ?? null, $pointer.'/title', $manifest);
            if (isset($quizIds[$quiz['id']])) {
                $this->fail('CONTENT_REFERENCE_DUPLICATE', 'Quiz IDs must be unique.', '/quizzes/'.$position.'/id', $manifest);
            }
            $quizIds[$quiz['id']] = true;
            $ids = $quiz['question_ids'] ?? null;
            if (! is_array($ids) || ! array_is_list($ids) || $ids === [] || count($ids) !== count(array_unique($ids, SORT_REGULAR))) {
                $this->fail('CONTENT_PACK_SCHEMA_INVALID', 'Quiz question IDs must be a non-empty unique list.', '/quizzes/'.$position.'/question_ids', $manifest);
            }
            foreach ($ids as $questionId) {
                if (! is_string($questionId) || ! isset($questionIds[$questionId])) {
                    $this->fail('CONTENT_REFERENCE_INVALID', 'A quiz question reference does not exist.', '/quizzes/'.$position.'/question_ids', $manifest);
                }
            }
        }

        return count($nodes) + count($lessonIds) + count($questionIds) + count($quizIds);
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $manifest
     */
    private function validateQuestion(array $question, int $position, array $manifest): void
    {
        $pointer = '/questions/'.$position;
        $this->objectKeys(
            $question,
            ['id', 'curriculum_node_reference', 'content_version', 'type', 'prompt', 'answer_contract', 'explanation'],
            ['id', 'curriculum_node_reference', 'content_version', 'type', 'prompt', 'options', 'answer_contract', 'explanation', 'maximum_score'],
            $pointer,
            $manifest,
        );
        $type = $question['type'] ?? null;
        $contract = $question['answer_contract'] ?? null;
        if (! in_array($type, ['single_choice', 'multiple_choice', 'short_text', 'numeric'], true)
            || ! is_int($question['content_version'] ?? null) || $question['content_version'] < 1
            || ! is_array($contract) || array_is_list($contract)
            || (isset($question['maximum_score']) && (! is_int($question['maximum_score']) && ! is_float($question['maximum_score'])))
            || (isset($question['maximum_score']) && ($question['maximum_score'] <= 0 || $question['maximum_score'] > 1000))) {
            $this->schemaFail('Question fields do not match Content Pack schema v1.', $pointer, $manifest);
        }
        $this->validateLocalizedText($question['prompt'] ?? null, $pointer.'/prompt', $manifest);
        $this->validateLocalizedText($question['explanation'] ?? null, $pointer.'/explanation', $manifest);
        if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
            $options = $question['options'] ?? null;
            if (! is_array($options) || ! array_is_list($options) || count($options) < 2 || count($options) > 20) {
                $this->fail('CONTENT_PACK_SCHEMA_INVALID', 'Choice questions require at least two options.', '/questions/'.$position.'/options', $manifest);
            }
            $optionIds = [];
            foreach ($options as $optionPosition => $option) {
                $optionPointer = $pointer.'/options/'.$optionPosition;
                if (! is_array($option) || array_is_list($option)) {
                    $this->schemaFail('Choice options must be objects.', $optionPointer, $manifest);
                }
                $this->exactKeys($option, ['id', 'label'], $optionPointer, $manifest);
                if (! is_string($option['id'] ?? null) || preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $option['id']) !== 1) {
                    $this->schemaFail('Choice option IDs are invalid.', $optionPointer.'/id', $manifest);
                }
                $this->validateLocalizedText($option['label'] ?? null, $optionPointer.'/label', $manifest);
                if (isset($optionIds[$option['id']])) {
                    $this->fail('CONTENT_REFERENCE_DUPLICATE', 'Choice option IDs must be unique.', $optionPointer.'/id', $manifest);
                }
                $optionIds[$option['id']] = true;
            }
            $this->exactKeys($contract, [$type === 'single_choice' ? 'correct_option_id' : 'correct_option_ids'], $pointer.'/answer_contract', $manifest);
            $correctIds = $type === 'single_choice'
                ? [$contract['correct_option_id'] ?? null]
                : ($contract['correct_option_ids'] ?? null);
            if (! is_array($correctIds) || ! array_is_list($correctIds) || $correctIds === []
                || count($correctIds) !== count(array_unique($correctIds, SORT_REGULAR))) {
                $this->fail('CONTENT_REFERENCE_INVALID', 'The correct option reference is invalid.', '/questions/'.$position.'/answer_contract', $manifest);
            }
            foreach ($correctIds as $correctId) {
                if (! is_string($correctId) || ! isset($optionIds[$correctId])) {
                    $this->fail('CONTENT_REFERENCE_INVALID', 'The correct option reference is invalid.', '/questions/'.$position.'/answer_contract', $manifest);
                }
            }
        } elseif ($type === 'short_text') {
            $this->exactKeys($contract, ['accepted_answers', 'case_sensitive'], $pointer.'/answer_contract', $manifest);
            $answers = $contract['accepted_answers'] ?? null;
            if (! is_array($answers) || ! array_is_list($answers) || $answers === [] || ! is_bool($contract['case_sensitive'] ?? null)) {
                $this->schemaFail('Short-text answer contract is invalid.', $pointer.'/answer_contract', $manifest);
            }
            foreach ($answers as $answer) {
                if (! is_string($answer) || $answer === '') {
                    $this->schemaFail('Short-text accepted answers must be non-empty strings.', $pointer.'/answer_contract/accepted_answers', $manifest);
                }
            }
        } else {
            $this->exactKeys($contract, ['value', 'tolerance'], $pointer.'/answer_contract', $manifest);
            if ((! is_int($contract['value'] ?? null) && ! is_float($contract['value'] ?? null))
                || (! is_int($contract['tolerance'] ?? null) && ! is_float($contract['tolerance'] ?? null))
                || $contract['tolerance'] < 0) {
                $this->schemaFail('Numeric answer contract is invalid.', $pointer.'/answer_contract', $manifest);
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private function validateManifestMetadata(array $manifest): void
    {
        if (! is_string($manifest['created_at'] ?? null)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $manifest['created_at']) !== 1
            || strtotime($manifest['created_at']) === false) {
            $this->schemaFail('created_at must be an RFC 3339 date-time.', '/created_at', $manifest);
        }

        $generator = $manifest['generator'] ?? null;
        if (! is_array($generator) || array_is_list($generator)) {
            $this->schemaFail('Generator metadata must be an object.', '/generator', $manifest);
        }
        $this->exactKeys($generator, ['name', 'version'], '/generator', $manifest);
        if (! is_string($generator['name']) || $generator['name'] === '' || mb_strlen($generator['name']) > 100
            || ! is_string($generator['version']) || $generator['version'] === '' || mb_strlen($generator['version']) > 50) {
            $this->schemaFail('Generator name and version are invalid.', '/generator', $manifest);
        }

        $provenance = $manifest['provenance'] ?? null;
        if (! is_array($provenance) || array_is_list($provenance)) {
            $this->schemaFail('Provenance metadata must be an object.', '/provenance', $manifest);
        }
        $this->exactKeys($provenance, ['rights_status', 'contains_student_pii', 'source_references'], '/provenance', $manifest);
        $sources = $provenance['source_references'] ?? null;
        if (! is_array($sources) || ! array_is_list($sources) || count($sources) > 100) {
            $this->schemaFail('Provenance source references are invalid.', '/provenance/source_references', $manifest);
        }
        foreach ($sources as $source) {
            if (! is_string($source) || $source === '' || mb_strlen($source) > 500) {
                $this->schemaFail('Provenance source references are invalid.', '/provenance/source_references', $manifest);
            }
        }

        $limits = $manifest['archive_limits'] ?? null;
        if (! is_array($limits) || array_is_list($limits)) {
            $this->schemaFail('Archive limits must be an object.', '/archive_limits', $manifest);
        }
        $this->exactKeys($limits, ['declared_uncompressed_bytes', 'declared_file_count'], '/archive_limits', $manifest);
        if (! is_int($limits['declared_uncompressed_bytes']) || $limits['declared_uncompressed_bytes'] < 1
            || $limits['declared_uncompressed_bytes'] > (int) config('modrik.content_import.maximum_archive_bytes')
            || ! is_int($limits['declared_file_count']) || $limits['declared_file_count'] < 1
            || $limits['declared_file_count'] > (int) config('modrik.content_import.maximum_file_count')) {
            $this->schemaFail('Archive limits are outside supported bounds.', '/archive_limits', $manifest);
        }
    }

    /** @param null|array<string, mixed> $manifest */
    private function validateAcademicScope(mixed $scope, string $pointer, ?array $manifest): void
    {
        if (! is_array($scope) || array_is_list($scope)) {
            $this->schemaFail('Academic scope must be an object.', $pointer, $manifest);
        }
        $this->objectKeys(
            $scope,
            ['track_reference', 'year_level', 'subject_references'],
            ['track_reference', 'board_reference', 'syllabus_version', 'year_level', 'subject_references'],
            $pointer,
            $manifest,
        );
        $subjects = $scope['subject_references'] ?? null;
        if (! $this->isReference($scope['track_reference'] ?? null)
            || (array_key_exists('board_reference', $scope) && $scope['board_reference'] !== null && ! $this->isReference($scope['board_reference']))
            || (array_key_exists('syllabus_version', $scope) && $scope['syllabus_version'] !== null
                && (! is_string($scope['syllabus_version']) || $scope['syllabus_version'] === '' || mb_strlen($scope['syllabus_version']) > 100))
            || ! is_string($scope['year_level'] ?? null) || $scope['year_level'] === '' || mb_strlen($scope['year_level']) > 40
            || ! is_array($subjects) || ! array_is_list($subjects) || $subjects === [] || count($subjects) > 20
            || count($subjects) !== count(array_unique($subjects, SORT_REGULAR))) {
            $this->schemaFail('Academic scope fields do not match preparation schema v1.', $pointer, $manifest);
        }
        foreach ($subjects as $subject) {
            if (! $this->isReference($subject)) {
                $this->schemaFail('Subject references are invalid.', $pointer.'/subject_references', $manifest);
            }
        }
    }

    /** @param null|array<string, mixed> $manifest */
    private function validateLocalizedText(mixed $value, string $pointer, ?array $manifest): void
    {
        if (! is_array($value) || array_is_list($value)
            || array_diff(array_keys($value), ['ar', 'en', 'fr']) !== []) {
            $this->schemaFail('Localized text must contain only ar, en, or fr values.', $pointer, $manifest);
        }
        foreach ($value as $text) {
            if (! is_string($text) || $text === '' || mb_strlen($text) > 10000) {
                $this->schemaFail('Localized text values must be non-empty and bounded.', $pointer, $manifest);
            }
        }
    }

    private function isUlid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value) === 1;
    }

    private function isReference(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Z0-9][A-Z0-9._:-]{2,99}$/', $value) === 1;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $allowed
     * @param  array<string, mixed>  $manifest
     */
    private function exactKeys(array $value, array $allowed, string $pointer, array $manifest): void
    {
        $this->objectKeys($value, $allowed, $allowed, $pointer, $manifest);
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $required
     * @param  list<string>  $allowed
     * @param  null|array<string, mixed>  $manifest
     */
    private function objectKeys(array $value, array $required, array $allowed, string $pointer, ?array $manifest): void
    {
        $missing = array_diff($required, array_keys($value));
        $extra = array_diff(array_keys($value), $allowed);
        if ($missing !== [] || $extra !== []) {
            $this->schemaFail('An object does not match its schema-required fields.', $pointer, $manifest);
        }
    }

    /** @param null|array<string, mixed> $manifest */
    private function schemaFail(string $message, string $pointer, ?array $manifest): never
    {
        $this->fail('CONTENT_SCHEMA_INVALID', $message, $pointer, $manifest);
    }

    /**
     * @param  null|array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function decodeObject(string $json, string $pointer, ?array $manifest = null): array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail('CONTENT_JSON_INVALID', 'A required JSON file is malformed.', $pointer, $manifest);
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            $this->fail('CONTENT_JSON_INVALID', 'A required JSON file must contain an object.', $pointer, $manifest);
        }

        return $decoded;
    }

    /** @param null|array<string, mixed> $manifest */
    private function fail(string $code, string $message, string $pointer, ?array $manifest = null): never
    {
        throw new ContentValidationException([[
            'pointer' => $pointer,
            'code' => $code,
            'message' => $message,
        ]], $manifest);
    }
}
