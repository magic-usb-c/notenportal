<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vorname'  => ['required', 'string', 'max:100'],
            'nachname' => ['required', 'string', 'max:100'],
            'email'    => [
                'required',
                'email',
                'max:255',
                Rule::unique('benutzer', 'email')->ignore($this->user()->benutzer_id, 'benutzer_id'),
            ],
        ];
    }
}
