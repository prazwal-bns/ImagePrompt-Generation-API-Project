<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\GeneratePromptRequest;

class ImageGenerationController extends Controller
{
    public function index()
    {

    }

    public function store(GeneratePromptRequest $request)
    {
        $user = $request->user();

        $image = $request->file('image');

        $originalFileName = $image->getClientOriginalName();
        // Remove any special characters from the file name for eg: "Hello World.png" to "Hello_World.png"
        $sanitizedName = preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($originalFileName, PATHINFO_FILENAME));

        $extension = $image->getClientOriginalExtension();
        $safeFileName = $sanitizedName . '_' . time() . '.' . $extension;

        $image->storeAs('uploads/images', $safeFileName, 'public');
    }
}
