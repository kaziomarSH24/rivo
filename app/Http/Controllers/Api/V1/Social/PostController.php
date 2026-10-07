<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Social\PostService;
use App\Http\Resources\Social\PostResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    protected $postService;

    public function __construct(PostService $postService)
    {
        $this->postService = $postService;
    }

    public function index(Request $request)
    {
        $posts = $this->postService->getAll();

        $resource = PostResource::collection($posts);
        return response_success('Posts retrieved successfully', [
            'data' => $resource->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required_without:media|string',
            'type' => 'sometimes|in:general,lost_pet,milestone',
            'pet_id' => 'sometimes|exists:pets,id',
            'media.*' => 'sometimes|file|mimes:jpeg,png,jpg,gif,mp4,mov|max:20480',
        ]);

        $data = $request->all();
        $data['user_id'] = $request->user()->id;

        $post = $this->postService->createPost($data, $request->file('media', []));

        return response_success('Post created successfully', new PostResource($post));
    }

    public function show(Post $post)
    {
        $post->load(['user', 'pet', 'media', 'comments.user', 'likes']);
        return response_success('Post retrieved successfully', new PostResource($post));
    }

    public function toggleLike(Request $request, Post $post)
    {
        $result = $this->postService->toggleLike($post, $request->user()->id);
        return response_success('Post ' . $result['status'], $result);
    }

    public function destroy(Post $post)
    {
        if ($post->user_id !== request()->user()->id) {
            return response_error('Unauthorized', [], 403);
        }

        $post->delete();
        return response_success('Post deleted successfully');
    }
}
