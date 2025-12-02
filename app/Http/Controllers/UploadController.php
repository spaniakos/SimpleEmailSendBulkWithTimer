<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UploadController extends Controller
{
    /**
     * Upload an image file
     */
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first('file'),
            ], 422);
        }

        $file = $request->file('file');
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

        // Store in public/images directory
        $file->move(public_path('images'), $filename);

        $url = asset('images/'.$filename);

        return response()->json([
            'location' => $url,
        ]);
    }

    /**
     * Upload a PDF file
     */
    public function uploadPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:pdf|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first('file'),
            ], 422);
        }

        $file = $request->file('file');
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

        // Store in public/pdf directory
        $file->move(public_path('pdf'), $filename);

        $url = asset('pdf/'.$filename);

        return response()->json([
            'location' => $url,
        ]);
    }
}
