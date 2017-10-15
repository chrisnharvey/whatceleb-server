<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Aws\Rekognition\RekognitionClient;

class CelebrityController extends Controller
{
    public function __construct()
    {
        $this->rekognition = new RekognitionClient([
            'version' => '2016-06-27',
            'region'      => 'eu-west-1',
            'credentials' => [
                'key'    => env('AWS_KEY'),
                'secret' => env('AWS_SECRET')
            ],
        ]);
    }
    public function findByFace(Request $request)
    {
        if ( ! $request->hasFile('photo')) {
            return response()->json([
                'error' => 'No photo uploaded'
            ], 400);
        }

        if ( ! $request->file('photo')->isValid()) {
            return response()->json([
                'error' => 'Invalid photo uploaded'
            ], 400);
        }

        $faces = $this->rekognition->recognizeCelebrities([
            'Image' => [
                'Bytes' => file_get_contents($request->photo->path())
            ]
        ]);

        $celeb = [];
        
        foreach ($faces['CelebrityFaces'] as $celeb) {
            $celeb = [
                'name' => $celeb['Name']
            ];

            foreach ($celeb['Urls'] as $url) {
                if (str_contains($url, 'imdb.com/name')) {

                    $celeb = [
                        'name' => $celeb['Name'],
                        'imdb_id' => str_after($url, 'imdb.com/name/')
                    ];

                    break; // Just get the first celeb with an IMDb ID ,we'll add support for multiple celebs later

                }
            }
        }

        if (empty($celeb)) {
            return response()->json([
                'error' => "Sorry, not sure who that is."
            ], 404);
        }

        return compact('celeb');
    }
}
