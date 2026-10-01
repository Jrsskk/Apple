<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class PdfQuizGeneratorService
{
    public function generate(UploadedFile $pdf, int $questionCount, string $difficulty, string $questionType): array
    {
        $text = $this->extractTextFromPdf($pdf);

        if (blank($text)) {
            throw ValidationException::withMessages([
                'pdf_file' => 'No readable text was found in the uploaded PDF. Please upload a lesson PDF with searchable text.',
            ]);
        }

        $facts = $this->extractFacts($text);

        if ($facts === []) {
            throw ValidationException::withMessages([
                'pdf_file' => 'The uploaded PDF did not contain enough clear lesson text to generate quiz questions.',
            ]);
        }

        $count = max(1, min($questionCount, 12));
        $generated = [];

        for ($index = 0; $index < $count; $index++) {
            $fact = $facts[$index % count($facts)];
            $generated[] = $this->buildQuestion($fact, $facts, $difficulty, $questionType);
        }

        return $generated;
    }

    private function extractTextFromPdf(UploadedFile $pdf): string
    {
        $storedPath = Storage::disk('local')->putFileAs('pdf-quiz-temp', $pdf, $pdf->getClientOriginalName() ?: 'generated-quiz.pdf');
        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $parser = new Parser();
            $parsed = $parser->parseFile($absolutePath);

            return trim(preg_replace('/\s+/', ' ', $parsed->getText()) ?? '');
        } catch (\Throwable $exception) {
            throw new FileException('The PDF could not be processed: '.$exception->getMessage());
        } finally {
            if (file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    private function extractFacts(string $text): array
    {
        $cleanText = preg_replace('/\s+/', ' ', strip_tags($text)) ?? $text;
        $segments = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9])/', $cleanText);

        $facts = [];
        foreach ($segments as $segment) {
            $cleanSegment = trim($segment);
            if ($cleanSegment === '') {
                continue;
            }

            $cleanSegment = preg_replace('/\s+/', ' ', $cleanSegment) ?? $cleanSegment;
            $length = mb_strlen($cleanSegment);

            if ($length < 25 || $length > 260) {
                continue;
            }

            if (preg_match('/[A-Za-z]{4,}/', $cleanSegment) !== 1) {
                continue;
            }

            if (preg_match('/\b(is|are|was|were|includes|contains|means|refers|called|describes|helps|defines|explains)\b/i', $cleanSegment) !== 1) {
                continue;
            }

            $facts[] = $cleanSegment;
        }

        if ($facts === []) {
            $paragraphs = preg_split('/\n+/', $cleanText);
            foreach ($paragraphs as $paragraph) {
                $segment = trim($paragraph);
                if ($segment === '') {
                    continue;
                }
                $segment = preg_replace('/\s+/', ' ', $segment) ?? $segment;
                if (mb_strlen($segment) < 25 || mb_strlen($segment) > 260) {
                    continue;
                }
                if (preg_match('/[A-Za-z]{4,}/', $segment) !== 1) {
                    continue;
                }
                $facts[] = $segment;
            }
        }

        $unique = [];
        foreach ($facts as $fact) {
            $key = mb_strtolower(trim($fact));
            if (! in_array($key, array_map('mb_strtolower', $unique), true)) {
                $unique[] = $fact;
            }
        }

        return array_slice($unique, 0, 30);
    }

    private function buildQuestion(string $fact, array $facts, string $difficulty, string $questionType): array
    {
        $points = match ($difficulty) {
            'easy' => 1,
            'hard' => 3,
            default => 2,
        };

        $type = in_array($questionType, ['multiple_choice', 'true_false', 'identification'], true)
            ? $questionType
            : 'multiple_choice';

        return match ($type) {
            'true_false' => $this->buildTrueFalseQuestion($fact, $points),
            'identification' => $this->buildIdentificationQuestion($fact, $facts, $points),
            default => $this->buildMultipleChoiceQuestion($fact, $facts, $points),
        };
    }

    private function buildMultipleChoiceQuestion(string $fact, array $facts, float $points): array
    {
        $options = [$fact];

        foreach ($facts as $candidate) {
            if ($candidate !== $fact && ! in_array($candidate, $options, true)) {
                $options[] = $candidate;
            }

            if (count($options) >= 4) {
                break;
            }
        }

        while (count($options) < 4) {
            $fallback = $this->fallbackQuestionText($facts);
            if ($fallback !== null && ! in_array($fallback, $options, true)) {
                $options[] = $fallback;
                continue;
            }
            break;
        }

        shuffle($options);

        $correctText = $fact;
        $optionSet = [];
        foreach ($options as $option) {
            $optionSet[] = [
                'option_text' => $option,
                'is_correct' => $option === $correctText,
                'match_key' => null,
            ];
        }

        return [
            'type' => 'multiple_choice',
            'question_text' => 'Which statement is supported by the PDF content?',
            'points' => $points,
            'explanation' => 'This answer is directly stated in the uploaded PDF material.',
            'options' => $optionSet,
        ];
    }

    private function buildTrueFalseQuestion(string $fact, float $points): array
    {
        $statement = trim($fact);

        return [
            'type' => 'true_false',
            'question_text' => 'The PDF states: "'.$statement.'" Is this statement true or false?',
            'points' => $points,
            'explanation' => 'The statement reflects information that was explicitly included in the uploaded PDF.',
            'options' => [
                ['option_text' => 'True', 'is_correct' => true, 'match_key' => null],
                ['option_text' => 'False', 'is_correct' => false, 'match_key' => null],
            ],
        ];
    }

    private function buildIdentificationQuestion(string $fact, array $facts, float $points): array
    {
        $answer = $this->extractIdentifier($fact);
        $options = [$answer];

        foreach ($facts as $candidate) {
            $identifier = $this->extractIdentifier($candidate);
            if ($identifier !== '' && $identifier !== $answer && ! in_array($identifier, $options, true)) {
                $options[] = $identifier;
            }

            if (count($options) >= 4) {
                break;
            }
        }

        while (count($options) < 4) {
            $fallback = $this->fallbackIdentifier($facts);
            if ($fallback !== null && ! in_array($fallback, $options, true)) {
                $options[] = $fallback;
                continue;
            }
            break;
        }

        shuffle($options);

        $optionSet = [];
        foreach ($options as $option) {
            $optionSet[] = [
                'option_text' => $option,
                'is_correct' => $option === $answer,
                'match_key' => null,
            ];
        }

        return [
            'type' => 'identification',
            'question_text' => 'Identify the key concept described in this statement: "'.$fact.'"',
            'points' => $points,
            'explanation' => 'The correct answer is the concept explicitly named in the uploaded PDF.',
            'options' => $optionSet,
        ];
    }

    private function extractIdentifier(string $sentence): string
    {
        $patterns = [
            '/^([A-Z][A-Za-z0-9\s\-]+?)\s+(?:is|are|was|were|means|refers to|includes|contains)\s+/u',
            '/([A-Z][A-Za-z0-9\s\-]+?)\s*,\s*(?:which|that|where)\s+/u',
            '/^([A-Z][A-Za-z0-9\s\-]+?)\s*:/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sentence, $match) === 1) {
                $identifier = trim($match[1]);
                if ($identifier !== '') {
                    return $identifier;
                }
            }
        }

        $parts = preg_split('/\s+/', $sentence);
        $identifier = implode(' ', array_slice($parts, 0, 5));

        return trim($identifier);
    }

    private function fallbackQuestionText(array $facts): ?string
    {
        foreach ($facts as $fact) {
            if (mb_strlen(trim($fact)) > 10) {
                return trim($fact);
            }
        }

        return null;
    }

    private function fallbackIdentifier(array $facts): ?string
    {
        foreach ($facts as $fact) {
            $identifier = $this->extractIdentifier($fact);
            if ($identifier !== '') {
                return $identifier;
            }
        }

        return null;
    }
}
