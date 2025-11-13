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
use Illuminate\Support\Facades\Auth;

class PromptGenerationController extends Controller
{

    public function __construct(private OpenAiService $openAiService){

    }

    /**
     * Get All Image Prompt Generations
     *
     * Get all image prompt generations for the authenticated user
     * Paginated
     *
     * @return \Illuminate\Http\JsonResponse
     * @authenticated
     */
    public function index(Request $request)
    {
        if(Auth::check()){
            $user = request()->user();
            $query = $user->imageGenerations();

            // Apply Searching: http://127.0.0.1:8000/api/v1/prompt-generations?per_page=25&search=foreground
            if($request->has('search') && !empty($request->get('search'))){
                $query->where('generated_prompt', 'like', '%' . $request->get('search') . '%');
            }

            // Apply Sorting: http://127.0.0.1:8000/api/v1/prompt-generations?per_page=25&sort=created_at&order=desc
            $allowedSortFields = ['created_at', 'updated_at','generated_prompt'];
            $sortField = 'created_at';
            $sortDirection = 'desc';

            if($request->has('sort') && !empty($request->get('sort'))){
                $sort = $request->sort;
                if (str_starts_with($sort, '-')){
                    $sortField = substr($sort, 1);
                    $sortDirection = 'desc';
                } else {
                    $sortField = $sort;
                    $sortDirection = 'asc';
                }
            }

            if(!in_array($sortField, $allowedSortFields)){
                $sortField = 'created_at';
                $sortDirection = 'desc';
            }

            // for descending order: http://127.0.0.1:8000/api/v1/prompt-generations?per_page=5&sort=-created_at 
            // for ascending order: http://127.0.0.1:8000/api/v1/prompt-generations?per_page=5&sort=created_at 

            $query->orderBy($sortField, $sortDirection);


            // for gettting specific page with per page and page number: http://127.0.0.1:8000/api/v1/prompt-generations?per_page=25&page=2
            $imageGenerations = $query->latest()->paginate($request->get('per_page'));


            // for getting specific page: http://127.0.0.1:8000/api/v1/prompt-generations?page=3
            // $imageGenerations = $query->latest()->paginate(10);
    
            return ImageGenerationResource::collection($imageGenerations);
        } else {
            return response()->json([
                'message' => 'User is not authenticated. Please login to continue.',
            ], 401);
        }
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
            if(Auth::check()){
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
            };

            return response()->json([
                'message' => 'User is not authenticated. Please login to continue.',
            ], 401);
            
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
