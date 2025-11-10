<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\GeneratePromptRequest;
use Illuminate\Support\Str;
use App\Services\OpenAiService;

class ImageGenerationController extends Controller
{

    public function __construct(private OpenAiService $openAiService){

    }

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
        $safeFileName = $sanitizedName . '_' . Str::random(10) . '.' . $extension;

        $imagePath = $image->storeAs('uploads/images', $safeFileName, 'public');

        $generatedPrompt = $this->openAiService->generatePromptFromImage($image);

        $imageGeneration = $user->imageGenerations()->create([
            'image_path' => $imagePath,
            'generated_prompt' => $generatedPrompt,
            'original_file_name' => $originalFileName,
            'file_size' => $image->getSize(),
            'mime_type' => $image->getClientMimeType(),
        ]);

        return response()->json($imageGeneration,201);
    }
}
