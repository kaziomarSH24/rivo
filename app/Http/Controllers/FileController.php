<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\FileUploadTrait;

class FileController extends Controller
{
    use FileUploadTrait;

    public function handleRequest(Request $request)
    {
        $image = $request->file('image');
        //call file upload trait
        if(!$image) {
            return response()->json(['error' => 'No image uploaded'], 400);
        }
        $imagePath = $this->handleFileUpload(
            $request,
            'image',
            'uploads',
            1920, // width
            1080, // height
            75, // quality
            true // forceWebp
        );
        dd($imagePath);

        return response()->json(['message' => 'Request handled successfully']);
    }
}
