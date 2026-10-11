<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\Social\PostCommentService;
use App\Http\Requests\Social\StorePostCommentRequest;
use App\Http\Resources\Social\PostCommentResource;
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    protected $commentService;

    public function __construct(PostCommentService $commentService)
    {
        $this->commentService = $commentService;
    }

    public function index(Request $request, Post $post)
    {
        // Fetch top-level comments with replies
        $comments = $this->commentService->getAll(function($q) use ($post) {
            $q->where('post_id', $post->id)
              ->whereNull('parent_id');
        });
        $comments->load(['user', 'replies.user']);

        $resource = PostCommentResource::collection($comments);
        return response_success('Comments retrieved', [
            'data' => $resource->items(),
            'pagination' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'total' => $comments->total(),
            ]
        ]);
    }

    public function store(StorePostCommentRequest $request, Post $post)
    {
        if ($post->turn_off_commenting) {
            return response_error('Commenting has been turned off for this post.', [], 403);
        }

        $validated = $request->validated();

        $data = [
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
            'user_id' => $request->user()->id,
        ];

        $comment = $this->commentService->createComment($post, $data);

        return response_success('Comment added successfully', new PostCommentResource($comment), 201);
    }

    public function destroy(Post $post, PostComment $comment)
    {
        if ($comment->user_id !== request()->user()->id && $post->user_id !== request()->user()->id) {
            return response_error('Unauthorized', [], 403);
        }

        $comment->delete();
        $post->decrement('comments_count');

        return response_success('Comment deleted successfully');
    }
}
