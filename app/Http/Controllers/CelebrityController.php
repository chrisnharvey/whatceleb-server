<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Aws\Rekognition\RekognitionClient;
use Tmdb\ApiToken as TmdbApiToken;
use Tmdb\Client as TmdbClient;
use Tmdb\Repository\ConfigurationRepository as TmdbConfig;
use Tmdb\Helper\ImageHelper;
use Tmdb\Repository\FindRepository as TmdbFind;

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

        $token  = new TmdbApiToken(env('TMDB_KEY'));
        $this->tmdb = new TmdbClient($token);

        $configRepository = new TmdbConfig($this->tmdb);
        $config = $configRepository->load();
        
        $this->image = new ImageHelper($config);
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

        $profile = [];
        
        foreach ($faces['CelebrityFaces'] as $celeb) {
            foreach ($celeb['Urls'] as $url) {
                if (str_contains($url, 'imdb.com/name')) {

                    $profile = [
                        'name' => $celeb['Name'],
                        'imdb_id' => str_after($url, 'imdb.com/name/')
                    ];

                    break 2; // Just get the first celeb with an IMDb ID ,we'll add support for multiple celebs later

                }
            }
        }

        if (empty($profile)) {
            return response()->json([
                'error' => "Sorry, not sure who that is."
            ], 404);
        }

        // Get TMDb info
        $repository = new TmdbFind($this->tmdb);
        $find = $repository->findBy($profile['imdb_id'], ['external_source' => 'imdb_id']);

        $personLookup = array_first($find->getPersonResults()->getPeople());

        if (empty($personLookup)) {
            return response()->json([
                'error' => "Sorry, not sure who that is."
            ], 404);
        }

        $repository = new \Tmdb\Repository\PeopleRepository($this->tmdb);
        $person = $repository->load($personLookup->getId());
        $person->setKnownFor($personLookup->getKnownFor());

        $knownFor = [];

        foreach ($person->getKnownFor() as $movie) {
            $knownFor[] = $this->createMovieArray($movie);
        }

        $profile = array_merge($profile, [
            'profile_image' => $this->getImage($person->getProfileImage(), 'h632'),
            'bio' => $person->getBiography(),
            'known_for' => $knownFor,            
        ]);

        return compact('profile');
    }

    protected function getImage($path, $size = 'original')
    {
        return 'https:'.$this->image->getUrl($path, $size);
    }

    protected function createMovieArray($movie)
    {
        return [
            'title' => $movie->getTitle(),
            'poster' => $this->getImage($movie->getPosterPath(), 'w185'),
            'year' => $movie->getReleaseDate()->format('Y')
        ];
    }
}
