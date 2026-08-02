<?php

declare(strict_types=1);

namespace App\Services\Settings;

use DOMDocument;
use DOMElement;
use Illuminate\Http\UploadedFile;

class SvgSafetyInspector
{
    public function isSafe(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension !== 'svg') {
            return true;
        }

        $content = @file_get_contents($file->getRealPath() ?: '');

        if (! is_string($content) || $content === '') {
            return false;
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $content) === 1) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $loaded = $document->loadXML($content, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
            $errors = libxml_get_errors();
            libxml_clear_errors();

            if (! $loaded || $errors !== [] || ! $document->documentElement instanceof DOMElement) {
                return false;
            }

            if (mb_strtolower($document->documentElement->localName) !== 'svg') {
                return false;
            }

            return $this->documentIsSafe($document);
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    private function documentIsSafe(DOMDocument $document): bool
    {
        $blockedElements = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'style'];

        foreach ($document->getElementsByTagName('*') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if (in_array(mb_strtolower($element->localName), $blockedElements, true)) {
                return false;
            }

            foreach ($element->attributes as $attribute) {
                $name = mb_strtolower($attribute->localName);
                $value = trim(mb_strtolower($attribute->value));

                if (str_starts_with($name, 'on') || str_contains($value, 'javascript:')) {
                    return false;
                }

                if (in_array($name, ['href', 'src'], true) && $value !== '' && ! str_starts_with($value, '#')) {
                    return false;
                }

                if ($name === 'style' && (
                    str_contains($value, '@import')
                    || preg_match('/url\s*\(\s*[\'\"]?(?!#)/i', $value) === 1
                )) {
                    return false;
                }
            }
        }

        return true;
    }
}
