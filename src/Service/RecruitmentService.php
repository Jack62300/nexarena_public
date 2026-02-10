<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class RecruitmentService
{
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5 Mo
    private const ALLOWED_FIELD_TYPES = ['text', 'textarea', 'select', 'radio', 'checkbox', 'email', 'number'];
    private const MAX_FIELDS = 20;

    public function __construct(
        private string $projectDir,
    ) {
    }

    public function processImage(UploadedFile $file): ?string
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_IMAGE_MIMES, true) || $file->getSize() > self::MAX_IMAGE_SIZE) {
            return null;
        }

        $filename = uniqid() . '.' . $file->guessExtension();
        $file->move($this->projectDir . '/public/uploads/recruitment', $filename);

        return $filename;
    }

    public function deleteImage(string $filename): void
    {
        $path = $this->projectDir . '/public/uploads/recruitment/' . basename($filename);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function validateFormFields(array $fields): array
    {
        $validated = [];
        $count = 0;

        foreach ($fields as $field) {
            if ($count >= self::MAX_FIELDS) {
                break;
            }

            if (!is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $type = trim((string) ($field['type'] ?? ''));
            $required = (bool) ($field['required'] ?? false);
            $placeholder = trim((string) ($field['placeholder'] ?? ''));

            if ($label === '' || !in_array($type, self::ALLOWED_FIELD_TYPES, true)) {
                continue;
            }

            $entry = [
                'label' => mb_substr($label, 0, 255),
                'type' => $type,
                'required' => $required,
                'placeholder' => mb_substr($placeholder, 0, 255),
            ];

            // Options for select/radio
            if (in_array($type, ['select', 'radio'], true)) {
                $options = [];
                if (isset($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as $opt) {
                        $opt = trim((string) $opt);
                        if ($opt !== '' && count($options) < 50) {
                            $options[] = mb_substr($opt, 0, 255);
                        }
                    }
                }
                if (empty($options)) {
                    continue; // select/radio need at least one option
                }
                $entry['options'] = $options;
            }

            $validated[] = $entry;
            $count++;
        }

        return $validated;
    }
}
