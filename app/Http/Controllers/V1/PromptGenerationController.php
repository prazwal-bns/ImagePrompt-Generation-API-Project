<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\V1\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\GeneratePromptRequest;
use App\Http\Resources\ImageGenerationResource;
use Illuminate\Support\Str;
use App\Services\OpenAiService;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;
use Exception;

class PromptGenerationController extends Controller
{

    public function __construct(private OpenAiService $openAiService){

    }

    /**
     * Get All Prompt Generations
     *
     * Get all prompt generations for the authenticated user
     *
     * @return \Illuminate\Http\JsonResponse
     * @authenticated
     */
    public function index()
    {
        $user = request()->user();
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated. Please login to continue.',
            ], 401);
        }

        $imageGenerations = $user->imageGenerations()->latest()->paginate(10);

        // $imageGenerations = [
        //     'id' => 1,
        //     'image_url' => 'https://via.placeholder.com/150',
        //     'generated_prompt' => 'A beautiful sunset over a calm ocean',
        //     'original_file_name' => 'sunset.jpg',
        //     'file_size' => 1000,
        //     'mime_type' => 'image/jpeg',
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ];
        // return response()->json($imageGenerations);

        return ImageGenerationResource::collection($imageGenerations);
    }

    /**
     * Generate Prompt
     * 
     * Generate a descriptive prompt from an image
     *
     * @param GeneratePromptRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @authenticated
     */
    public function store(GeneratePromptRequest $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated. Please login to continue.',
                ], 401);
            }

            // Validate the request
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

            return new ImageGenerationResource($imageGeneration);
            
        } catch (RateLimitException $e) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'OpenAI API rate limit has been exceeded. Please try again later.',
                'details' => $e->getMessage(),
            ], 429);
            
        } catch (ErrorException $e) {
            return response()->json([
                'error' => 'OpenAI API error',
                'message' => 'An error occurred while processing your request with OpenAI.',
                'details' => $e->getMessage(),
            ], 500);
            
        } catch (TransporterException $e) {
            return response()->json([
                'error' => 'Connection error',
                'message' => 'Failed to connect to OpenAI API. Please check your internet connection and try again.',
                'details' => $e->getMessage(),
            ], 503);
            
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Unexpected error',
                'message' => 'An unexpected error occurred while processing your request.',
                'details' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
