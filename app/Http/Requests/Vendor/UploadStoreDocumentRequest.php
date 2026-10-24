<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\BaseRequest;

class UploadStoreDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:business_registration_certificate,business_license,tax_certificate,additional_document',
            'document' => 'required|file|mimes:pdf,jpg,png,jpeg|max:10240',
        ];
    }
}