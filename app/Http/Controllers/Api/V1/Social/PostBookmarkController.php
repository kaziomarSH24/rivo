<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Http\Resources\Social\PostResource;
use App\Models\Post;
use App\Models\PostBookmark;
use Illuminate\Http\Request;

class PostBookmarkController extends Controller
{
    /**
     * List authenticated user's saved/bookmarked posts
     */
    public function index(Request $request)
    {
        $posts = $request->user()
            ->bookmarkedPosts()
            ->with(['user', 'pet', 'media', 'reactions'])
            ->latest('post_bookmarks.created_at')
            ->paginate($request->input('per_page', 15));

        return response_success('Saved posts retrieved successfully', PostResource::collection($posts)->response()->getData(true));
    }

    /**
     * Toggle save/unsave (bookmark) a post
     */
    public function toggle(Request $request, Post $post)
    {
        $user = $request->user();

        $bookmark = PostBookmark::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        if ($bookmark) {
            $bookmark->delete();
            return response_success('Post removed from saved posts', [
                'post_id' => $post->id,
                'is_bookmarked' => false,
            ]);
        }

        PostBookmark::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        return response_success('Post saved successfully', [
            'post_id' => $post->id,
            'is_bookmarked' => true,
        ]);
    }
}
