<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'title' => [$presence, 'required', 'string', 'max:255'],
            'content' => [$presence, 'required', 'string', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->isMethod('patch') && ! $this->hasAny(['title', 'content'])) {
                $validator->errors()->add('note', 'Provide title or content to update.');
            }
        }];
    }
}
