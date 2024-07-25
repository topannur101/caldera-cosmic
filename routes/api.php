<?php

use App\Models\User;
use App\Models\InsOmvMetric;
use App\Models\InsOmvRecipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
        'user_1_emp_id' => 'required|exists:users,emp_id',
        'user_2_emp_id' => 'nullable|exists:users,emp_id',
        'eval' => 'required|in:too_soon,on_time,too_late',
        'start_at' => 'required|date_format:Y-m-d H:i:s',
        'end_at' => 'required|date_format:Y-m-d H:i:s',
        'shift' => 'nullable|integer|min:1|max:3'
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

    $omvMetric = new InsOmvMetric();
    $omvMetric->ins_omv_recipe_id = $request->recipe_id;
    $omvMetric->user_1_id = $user1->id;
    $omvMetric->user_2_id = $user2->id ?? null;
    $omvMetric->eval = strtolower($request->eval); // converting eval to lowercase
    $omvMetric->start_at = $request->start_at;
    $omvMetric->end_at = $request->end_at;
    $omvMetric->shift = $request->shift;
    $omvMetric->save();

    return response()->json([
        'status' => 'valid',
        'msg' => 'none',
    ], 200);
});
