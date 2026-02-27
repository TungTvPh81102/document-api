<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Http\Resources\MediaResource;
use App\Services\MediaService;
use App\Services\ModelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use App\Exceptions\MediaUploadException;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * MediaController — 1 controller duy nhất xử lý TẤT CẢ models
 * 
 * Routes:
 *   POST   /api/v1/media/{modelType}/{modelId}/{collection}   → upload()
 *   DELETE /api/v1/media/{mediaId}                            → destroy()
 *   GET    /api/v1/media/{modelType}/{modelId}                → index()
 * 
 * {modelType} là snake_case: user, post, product
 * 
 * Không dùng if/switch để phân biệt model
 * Thay vào đó dùng ModelResolver → resolve model dynamically
 * 
 * Business logic → MediaService
 * Authorization → MediaPolicy
 */
class MediaController extends Controller
{
    protected MediaService $mediaService;
    protected ModelResolver $modelResolver;

    public function __construct(
        MediaService $mediaService,
        ModelResolver $modelResolver,
    ) {
        $this->mediaService = $mediaService;
        $this->modelResolver = $modelResolver;

        // Middleware auth + throttle cho tất cả routes
        $this->middleware('auth:sanctum');
    }

    /**
     * Upload media file
     * 
     * POST /api/v1/media/{modelType}/{modelId}/{collection}
     * 
     * @param UploadMediaRequest $request
     * @param string $modelType snake_case (user, post, product)
     * @param int $modelId
     * @param string $collection avatar, thumbnail, gallery, documents
     * @return JsonResponse
     */
    public function upload(
        UploadMediaRequest $request,
        string $modelType,
        int $modelId,
        string $collection,
    ): JsonResponse {
        try {
            // Resolve model
            $model = $this->modelResolver->getModel($modelType, $modelId);

            // Authorization: Check user có quyền upload vào model này không
            if (!Gate::allows('upload', [$model, $collection])) {
                throw new AuthorizationException('Bạn không có quyền thực hiện hành động này');
            }

            // Validate collection
            if (!$this->modelResolver->isValidCollectionForModel($modelType, $collection)) {
                return response()->json([
                    'success' => false,
                    'message' => "Collection '{$collection}' không được hỗ trợ bởi model '{$modelType}'",
                ], 422);
            }

            // Get file từ request
            $file = $request->file('file');

            // Upload (Cloudinary upload xảy ra ở đây)
            // Nếu single-file collection → syncSingleFile (xóa cũ rồi upload mới)
            if (
                $this->mediaService->getCollectionMedia($model, $collection)->count() > 0
                && config("media.collection_rules.{$collection}.type") === 'single'
            ) {
                $media = $this->mediaService->syncSingleFile($model, $collection, $file);
            } else {
                $media = $this->mediaService->upload($model, $collection, $file);
            }

            return response()->json([
                'success' => true,
                'message' => 'Upload thành công',
                'data' => new MediaResource($media),
            ], 201);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (MediaUploadException $e) {
            $statusCode = $e->getCode() ?: 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $statusCode);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload thất bại, vui lòng thử lại',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Delete media
     * 
     * DELETE /api/v1/media/{mediaId}
     * 
     * @param int $mediaId
     * @return JsonResponse
     */
    public function destroy(int $mediaId): JsonResponse
    {
        try {
            $media = Media::findOrFail($mediaId);

            // Authorization: Check user có quyền xóa media này không
            if (!Gate::allows('delete', $media)) {
                throw new AuthorizationException('Bạn không có quyền thực hiện hành động này');
            }

            // Delete
            $this->mediaService->delete($media);

            return response()->json([
                'success' => true,
                'message' => 'Xóa thành công',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Media không tồn tại',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xóa thất bại, vui lòng thử lại',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * List media của model
     * 
     * GET /api/v1/media/{modelType}/{modelId}
     * 
     * Response structure:
     * {
     *   "success": true,
     *   "data": {
     *     "avatar": { ... },           ← null nếu chưa có
     *     "gallery": [ {...}, {...} ],
     *     "documents": [ {...} ]
     *   }
     * }
     * 
     * @param string $modelType
     * @param int $modelId
     * @return JsonResponse
     */
    public function index(string $modelType, int $modelId): JsonResponse
    {
        try {
            // Resolve model
            $model = $this->modelResolver->getModel($modelType, $modelId);

            // Authorization: Check user có quyền xem media của model này không
            // (có thể là owner hoặc admin)
            if (!$this->canViewModel($model)) {
                throw new AuthorizationException('Bạn không có quyền xem thông tin này');
            }

            // Get collections cho model này
            $collections = $this->modelResolver->getCollections($modelType);

            // Build response
            $mediaData = [];

            foreach ($collections as $collection) {
                $allMedia = $this->mediaService->getCollectionMedia($model, $collection);

                if (count($allMedia) === 0) {
                    // Collection rỗng
                    $mediaData[$collection] = config("media.collection_rules.{$collection}.type") === 'single'
                        ? null
                        : [];
                } elseif (config("media.collection_rules.{$collection}.type") === 'single') {
                    // Single file → trả về 1 object
                    $mediaData[$collection] = new MediaResource($allMedia->first());
                } else {
                    // Multiple → trả về array
                    $mediaData[$collection] = MediaResource::collection($allMedia);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $mediaData,
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Model không tồn tại',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Check user có quyền xem model này không
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return bool
     */
    protected function canViewModel($model): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Admin có quyền xem tất cả
        if ($user->hasRole('admin') || $user->hasPermissionTo('super-admin')) {
            return true;
        }

        // Owner có quyền xem của mình
        // Nếu model có user_id → check user_id
        if (isset($model->user_id)) {
            return $model->user_id === $user->id;
        }

        // Nếu model là User → check ID
        if ($model->id === $user->id) {
            return true;
        }

        return false;
    }
}
