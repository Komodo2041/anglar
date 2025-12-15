<?php

namespace App\Http\Controllers;

use App\Http\Services\CommentService;
use App\Models\Post;
use App\Models\Todo;
use Illuminate\Http\Request;

class FeedController extends Controller
{

    const TAKE = 20;

    public function get(Request $req)
    {
        $friend_ids = [];
        if (auth()->check()) {
            $friend_ids = user()->follows()
                ->wherePivot('status', 1)
                ->pluck('friend_id')
                ->toArray();
        }

        $page = (int) $req->input('page');


        $perPage = FeedController::TAKE;
        $offset = ($page - 1)*$perPage;

        $posts = Post::when(auth()->check(), function($q) use ($friend_ids) {
                $q->where(function($q) use ($friend_ids) {
                    $q->where('user_id', user()->id);
                    $q->orWhere(function($q) use ($friend_ids) {
                        $q->whereIn('user_id', $friend_ids);
                        $q->where('status', 1);
                    });
                });
            }, function($q) {
                $q->where('status', 1);
            })
            ->with(['user', 'comments.user', 'reactions.user'])
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function($post) {
                return [
                    'id' => $post->id,
                    'type' => 'post',
                    'user_id' => $post->user_id,
                    'user' => $post->user,
                    'content' => $post->content,
                    'status' => $post->status,
                    'comments' => CommentService::parse($post),
                    'reactions' => $post->formattedReactionsList(),
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                ];
            });


        $feed = $posts->values();
        
        $all = Post::when(auth()->check(), function($q) use ($friend_ids) {
                $q->where(function($q) use ($friend_ids) {
                    $q->where('user_id', user()->id);
                    $q->orWhere(function($q) use ($friend_ids) {
                        $q->whereIn('user_id', $friend_ids);
                        $q->where('status', 1);
                    });
                });
            }, function($q) {
                $q->where('status', 1);
            })
            ->with(['user', 'comments.user', 'reactions.user'])->count();
 

        return response()->json([
            'feed' => $feed,
            'totalPages' => ceil($all/$perPage)
        ], 200);
    }
}
