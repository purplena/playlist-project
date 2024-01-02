<?php

namespace App\Console\Commands;

use App\Http\DTO\PlaylistDataDTO;
use App\Http\DTO\SongDTO;
use App\Models\Company;
use App\Models\Song;
use App\Repositories\SongRepository;
use App\Services\SpotifyApi;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPlaylist extends Command
{
    public function __construct(protected SpotifyApi $spotifyApi, private SongRepository $songRepository)
    {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-playlist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronisation between Spotify and Database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        foreach (Company::all() as $company) {
            try {
                $api = $this->spotifyApi->getCompanyApiInstance($company);
            } catch (Throwable $t) {
                Log::error('Problem with refresh token. Reconnection to spotify is needed', ['company' => $company]);
                $company->update([
                    'spotify_playlist_data' => null,
                ]);

                continue;
            }

            try {
                $playlistId = Arr::get($company->spotify_playlist_data, 'playlist.id');
                if (collect($this->getAllPlaylists($api, $company))->contains('id', $playlistId)) {
                    $this->areSnapshotIdsIdentical($api, $company, $playlistId);
                } else {
                    $company->update([
                        'spotify_playlist_data' => null,
                    ]);
                }
            } catch (Throwable $t) {
                Log::error('Problem with user_id in spotify_playlist_data. spotify_playlist_data is corrupted', ['company' => $company]);

                continue;
            }
        }
    }

    protected function areSnapshotIdsIdentical($api, $company, $playlistId)
    {
        $snapshotIdDB = Arr::get($company->spotify_playlist_data, 'playlist.snapshot_id');

        if ($api->getPlaylist($playlistId)->snapshot_id === $snapshotIdDB) {
            $this->addTracksToPlaylist($api, $company, $playlistId);
            $this->updatePlaylistSnapshot($api, $company, $playlistId);
        } else {
            $this->syncPlaylist($company, $api, $playlistId);
        }
    }

    protected function updatePlaylistSnapshot($api, $company, $playlistId)
    {
        $company->update([
            'spotify_playlist_data' => array_merge($company->spotify_playlist_data, ['playlist' => PlaylistDataDTO::fromObjectToArray($api->getPlaylist($playlistId)),
            ]),
        ]);
    }

    protected function getSongsSpotify($api, $playlistId)
    {
        return collect($api->getPlaylistTracks($playlistId)->items)->map(function ($track) {
            return SongDTO::fromObjectToArray($track->track);
        });
    }

    protected function syncPlaylist($company, $api, $playlistId): void
    {
        $songsSpotifyIdsDB = $company->songs()->pluck('spotify_id')->toArray();
        $songsSpotify = $this->getSongsSpotify($api, $playlistId);

        $songsAddedToSpotify = $songsSpotify
            ->filter(function ($songSpotify) use ($songsSpotifyIdsDB) {
                return ! in_array($songSpotify['spotify_id'], $songsSpotifyIdsDB);
            })
            ->each(function ($songAdded) use ($company) {
                $song = Song::where(['spotify_id' => $songAdded['spotify_id']])
                    ->firstOr(function () use ($songAdded) {
                        return Song::create($songAdded);
                    });
                $company->songs()->attach($song->id);
            });

        $this->addTracksToPlaylist($api, $company, $playlistId);
        $this->updatePlaylistSnapshot($api, $company, $playlistId);
    }

    protected function addTracksToPlaylist($api, $company, $playlistId): void
    {
        $songsSpotifyIds = $this->getSongsSpotify($api, $playlistId)->pluck('spotify_id')->toArray();

        $songsDeletedFromSpotify = $company->songs
            ->filter(function ($companySong) use ($songsSpotifyIds) {
                return ! in_array($companySong->spotify_id, $songsSpotifyIds);
            });

        $this->getSongsToAddToSpotify($company)
            ->filter(function ($songToAdd) use ($songsSpotifyIds) {
                return ! in_array($songToAdd->song->spotify_id, $songsSpotifyIds);
            })
            ->filter(function ($songToAdd) use ($songsDeletedFromSpotify) {
                return ! in_array($songToAdd->song->spotify_id, $songsDeletedFromSpotify->pluck('spotify_id')->toArray());
            })
            ->each(function ($songToAdd) use ($api, $playlistId, $company) {
                $api->addPlaylistTracks($playlistId, [
                    $songToAdd->song->spotify_id,
                ]);
                $company->songs()->attach($songToAdd->song->id);
            });

        $songsDeletedFromSpotify
            ->each(function ($companySong) use ($company) {
                $company->songs()->detach($companySong->id);
            });
    }

    protected function getAllPlaylists($api, $company): array
    {
        $userId = Arr::get($company->spotify_playlist_data, 'user_id');

        $continue = true;
        $nbElementsSeen = 0;
        $items = [];
        $limitPerPage = 50;

        while ($continue) {
            $playlists = $api->getUserPlaylists($userId, [
                'limit' => $limitPerPage,
                'offset' => $nbElementsSeen,
            ]);
            $items = array_merge($items, $playlists->items);
            $nbElementsSeen += $limitPerPage;
            if ($nbElementsSeen >= $playlists->total) {
                $continue = false;
            }
        }

        return $items;
    }

    protected function getSongsToAddToSpotify($company): Collection
    {
        $requestedSongs = $this->songRepository->getRequestedSongsWithUpvotesCount($company);
        $topScores = $this->getTopThreeScores($requestedSongs);
        $companySongsIds = $company->songs()->pluck('song_id')->toArray();

        return $requestedSongs->filter(function ($requestedSong) use ($topScores) {
            return in_array($requestedSong->upvotes_count, $topScores);
        })
            ->filter(function ($requestedSong) use ($companySongsIds) {
                return ! in_array($requestedSong->song_id, $companySongsIds);
            })->values();
    }

    protected function getTopThreeScores($requestedSongs): array
    {
        return collect($requestedSongs->pluck('upvotes_count'))
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->sortDesc()
            ->slice(0, 3)
            ->toArray();
    }
}
