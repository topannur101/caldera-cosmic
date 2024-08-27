<?php

use App\Models\User;
use Illuminate\Support\Str;
use App\Models\InsOmvMetric;
use App\Models\InsOmvRecipe;
use Illuminate\Http\Request;
use App\Models\InsOmvCapture;
use App\Models\InsRubberBatch;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/omv-recipes', function() {
    $recipes = InsOmvRecipe::all()->map(function($recipe) {
        // Parse the steps JSON field
        $steps = json_decode($recipe->steps);

        // Parse the capture_points JSON field
        $capture_points = json_decode($recipe->capture_points);

        return [
            'id' => $recipe->id,
            'type' => $recipe->type,
            'name' => $recipe->name,
            'capture_points' => $capture_points,
            'steps' => $steps,
        ];
    });

    return response()->json($recipes);
});

Route::post('/omv-metric', function (Request $request) {
    $validator = Validator::make($request->all(), [
        'recipe_id' => 'required|exists:ins_omv_recipes,id',
        'code' => 'nullable|string|max:20',
        'line' => 'required|integer|min:1|max:99',
        'team' => 'required|in:A,B,C',
        'user_1_emp_id' => 'required|exists:users,emp_id',
        'user_2_emp_id' => 'nullable|exists:users,emp_id',
        'eval' => 'required|in:too_soon,on_time,too_late',
        'start_at' => 'required|date_format:Y-m-d H:i:s',
        'end_at' => 'required|date_format:Y-m-d H:i:s',
        'captured_images' => 'nullable|array',
        'captured_images.*.stepIndex' => 'required|integer',
        'captured_images.*.captureTime' => 'required|numeric',
        'captured_images.*.image' => [
            'required',
            'string',
            'regex:/^data:image\/(?:jpeg|png|jpg|gif);base64,/',
            function ($attribute, $value, $fail) {
                $imageData = base64_decode(explode(',', $value)[1]);
                $image = imagecreatefromstring($imageData);
                if (!$image) {
                    $fail('The '.$attribute.' must be a valid base64 encoded image.');
                }
            },
        ],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'invalid',
            'msg' => $validator->errors()->all(),
        ], 400);
    }

    $user1 = User::where('emp_id', $request->user_1_emp_id)->first();
    $user2 = User::where('emp_id', $request->user_2_emp_id)->first();

    $errors = [];

    if (!$user1) {
        $errors[] = "The emp_id '{$request->user_1_emp_id}' on user_1_emp_id does not exist.";
    }

     if (!empty($errors)) {
        return response()->json([
            'status' => 'invalid',
            'msg' => $errors,
        ], 400);
    }

    $code = strtoupper(trim($request->code));
    $batch = $code ? InsRubberBatch::firstOrCreate(['code' => $code]) : null;

    $batch->omv_eval = strtolower($request->eval);
    $batch->save();

    $omvMetric = new InsOmvMetric();
    $omvMetric->ins_omv_recipe_id = $request->recipe_id;
    $omvMetric->line = $request->line;
    $omvMetric->team = $request->team;
    $omvMetric->user_1_id = $user1->id;
    $omvMetric->user_2_id = $user2->id ?? null;
    $omvMetric->eval = strtolower($request->eval); // converting eval to lowercase
    $omvMetric->start_at = $request->start_at;
    $omvMetric->end_at = $request->end_at;
    $omvMetric->ins_rubber_batch_id = $batch->id ?? null;
    $omvMetric->save();
    
    $captureMessages = [];
    
    foreach ($request->captured_images as $index => $capturedImage) {
        try {
            $imageData = base64_decode(explode(',', $capturedImage['image'])[1]);
            $extension = explode('/', mime_content_type($capturedImage['image']))[1];
            
            $fileName = sprintf(
                '%s_%s_%s_%s.%s',
                $omvMetric->id,
                $capturedImage['stepIndex'],
                $capturedImage['captureTime'],
                Str::random(8),
                $extension
            );
            
            if (!Storage::put('/public/omv-captures/'.$fileName, $imageData)) {
                throw new Exception("Failed to save image file.");
            }
    
            $omvCapture = new InsOmvCapture();
            $omvCapture->ins_omv_metric_id = $omvMetric->id;
            $omvCapture->file_name = $fileName;
            $omvCapture->save();
    
        } catch (Exception $e) {
            $captureMessages[] = "Error saving capture {$index}: " . $e->getMessage();
        }
    }
    
    $responseMessage = 'OMV Metric saved successfully.';
    if (!empty($captureMessages)) {
        $responseMessage .= ' However, there were issues with some captures: ' . implode(', ', $captureMessages);
    }
    
    return response()->json([
        'status' => 'valid',
        'msg' => $responseMessage,
    ], 200);
});
