<?php

namespace App\Http\Requests\Teacher;

use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() || $this->user()?->isAdmin();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $schoolClass = SchoolClass::find($this->input('school_class_id'));

            if (! $schoolClass) {
                return;
            }

            if ($user?->isTeacher() && $schoolClass->teacher_id !== $user->id) {
                $validator->errors()->add('school_class_id', 'You may only create quizzes for your own classes.');

                return;
            }

            if ((int) $schoolClass->subject_id !== (int) $this->input('subject_id')) {
                $validator->errors()->add('subject_id', 'The selected subject does not belong to this class.');
            }
        });
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'subject_id' => 'required|exists:subjects,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'starts_at' => 'nullable|date',
            'deadline' => 'nullable|date|after_or_equal:starts_at',
            'duration_minutes' => 'required|integer|min:1|max:480',
            'max_attempts' => 'required|integer|min:1|max:10',
            'passing_score' => 'required|numeric|min:0|max:100',
            'randomize_questions' => 'boolean',
            'randomize_choices' => 'boolean',
            'show_results' => 'boolean',
            'allow_review' => 'boolean',
            'status' => 'in:draft,published,archived',
            'questions' => 'array',
            'questions.*.type' => 'required|in:multiple_choice,true_false,identification,matching,sequencing,image_based,multiple_selection,drag_drop',
            'questions.*.question_text' => 'required|string',
            'questions.*.points' => 'required|numeric|min:0',
            'questions.*.options' => 'array',
        ];
    }
}
