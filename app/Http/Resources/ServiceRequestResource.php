<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'request_no'   => $this->request_no,
            'status'       => $this->status,
            'submitted_at' => $this->submitted_at,
            'service'      => [
                'svc_code' => $this->service->svc_code,
                'title'    => $this->service->getTitle($request->header('X-Locale', 'en')),
            ],
            'answers'      => $this->answers->mapWithKeys(function ($answer) {
                return [$answer->field->field_key => $this->formatValue($answer)];
            }),
            'attachments'  => $this->attachments->map(function ($attachment) {
                return [
                    'file_name' => $attachment->file_name,
                    'file_path' => $attachment->file_path,
                ];
            }),
        ];
    }

    private function formatValue($answer)
    {
        if ($answer->value_json) return $answer->value_json;
        if ($answer->value_number) return $answer->value_number;
        if ($answer->value_date) return $answer->value_date;
        return $answer->value_text;
    }
}
