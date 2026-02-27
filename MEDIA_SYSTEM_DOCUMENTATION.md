# Polymorphic Media Upload System (Laravel Sanctum + Spatie + Cloudinary)

## Overview

Hệ thống media polymorphic cho phép upload file tới **bất kỳ model nào** (User, Post, Product, v.v.) mà **không cần tạo code riêng per-model**.

### Key Features

✅ **Polymorphic** - 1 Controller, 1 Service xử lý tất cả models  
✅ **Config-driven** - Thêm model mới chỉ bằng config + trait  
✅ **Cloudinary Storage** - Tất cả file lưu trên Cloudinary  
✅ **Smart Collections** - `avatar` (single), `gallery` (multiple), `documents` (PDF), etc  
✅ **Authorization** - Policy-based, hỗ trợ ownership check  
✅ **Error Handling** - Rollback + logging nếu upload fail

---

## Installation & Setup

### 1. Environment Variables

Thêm vào `.env`:

```dotenv
CLOUDINARY_URL=cloudinary://your_api_key:your_api_secret@your_cloud_name
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
```

### 2. Run Migrations

```bash
php artisan migrate
```

Migrations tạo:

- `media` table (Spatie Media Library)
- `posts` table
- `products` table

### 3. Add Trait to existing models

```php
// app/Models/User.php
use App\Traits\HasMediaCollections;

class User extends Model {
    use HasMediaCollections;
}
```

---

## Architecture & Components

### A. Config Files

#### `config/media.php` - Model Registry

```php
'models' => [
    'user' => [
        'class' => \App\Models\User::class,
        'table' => 'users',
        'owner_field' => 'id',
        'collections' => ['avatar', 'documents'],
    ],
    'post' => [
        'class' => \App\Models\Post::class,
        'table' => 'posts',
        'owner_field' => 'user_id',  // Post belongs to User
        'collections' => ['thumbnail', 'gallery', 'documents'],
    ],
],

'collection_rules' => [
    'avatar' => ['type' => 'single', 'mimes' => [...], 'max_size' => 10*1024*1024],
    'gallery' => ['type' => 'multiple', 'mimes' => [...], 'max_size' => 10*1024*1024],
    'documents' => ['type' => 'multiple', 'mimes' => ['pdf'], ...],
],
```

**Thêm model mới:**

```php
'category' => [
    'class' => \App\Models\Category::class,
    'table' => 'categories',
    'owner_field' => null,  // null = chỉ admin upload
    'collections' => ['avatar'],
],
```

Rồi add trait vào model → hoàn tất ✓

#### `config/filesystems.php` - Cloudinary Disk

```php
'disks' => [
    'cloudinary' => [
        'driver' => 'cloudinary',
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],
],
```

#### `config/media-library.php` - Spatie Config

```php
'disk_name' => env('MEDIA_DISK', 'cloudinary'),
'path_generator' => \App\Media\CloudinaryPathGenerator::class,
```

### B. Core Classes

#### `app/Media/MediaCollectionConfig.php`

Single source of truth cho collection rules.

```php
// Get validation rules
MediaCollectionConfig::getValidationRules('avatar');
// ['file' => 'required|file|mimes:jpg,png,webp|max:10240']

// Get allowed extensions
MediaCollectionConfig::getAllowedExtensions('gallery');
// ['jpg', 'png', 'webp', 'mp4']

// Check if single file
MediaCollectionConfig::isSingleFile('avatar');
// true
```

#### `app/Media/CloudinaryPathGenerator.php`

Tạo path structure trên Cloudinary:

```
user/1/avatar/profile.jpg
post/42/gallery/photo_1.jpg
post/42/gallery/photo_2.jpg
product/15/documents/spec.pdf
```

#### `app/Traits/HasMediaCollections`

Trait cho tất cả models có media:

```php
class Post extends Model {
    use HasMediaCollections;  // Tự động các collection từ config
}

// Dùng public methods:
$post->getMediaByCollection('gallery');        // Lấy gallery media
$post->getFirstMedia('thumbnail');             // Lấy 1 file từ thumbnail
$post->getMediaUrl('avatar');                  // Lấy URL của avatar
```

#### `app/Services/ModelResolver.php`

Map modelType string → Model class:

```php
$resolver = new ModelResolver();

$resolver->resolve('post');              // 'App\Models\Post'
$resolver->getModel('post', 42);         // Post instance với ID 42
$resolver->getSupportedModelTypes();     // ['user', 'post', 'product']
$resolver->getCollections('post');       // ['thumbnail', 'gallery', 'documents']
```

#### `app/Services/MediaService.php`

Master service cho tất cả media operations:

```php
// Upload
$media = $mediaService->upload($post, 'gallery', $file);

// Sync single file (xóa cũ rồi upload mới)
$media = $mediaService->syncSingleFile($user, 'avatar', $file);

// Delete
$mediaService->delete($media);

// Get URL
$url = $mediaService->getMediaUrl($media);
```

### C. Requests & Responses

#### `app/Http/Requests/Media/UploadMediaRequest`

Auto-validate based on collection rules:

```php
// Chỉ implement authorize() = true
// rules() và messages() tự động từ MediaCollectionConfig
```

#### `app/Http/Resources/MediaResource`

JSON response format:

```json
{
    "id": 12,
    "uuid": "abc-xyz-123",
    "name": "photo.jpg",
    "collection": "gallery",
    "mime_type": "image/jpeg",
    "size": 204800,
    "url": "https://res.cloudinary.com/.../user/1/avatar/photo.jpg",
    "model_type": "post",
    "model_id": 42,
    "created_at": "2026-02-27T12:00:00Z"
}
```

### D. Authorization

#### `app/Policies/MediaPolicy.php`

Kiểm tra ownership:

```php
// upload(User $user, Model $model, string $collection)
// - Super admin → bypass
// - Nếu owner_field set: kiểm tra $model->$owner_field === $user->id
// - Nếu owner_field null: chỉ admin

// delete(User $user, Media $media)
// - Dùng upload policy để check
```

#### Registered in `AuthServiceProvider`

```php
protected $policies = [
    Media::class => MediaPolicy::class,
];
```

### E. Exception Handling

#### `app/Exceptions/MediaUploadException`

```php
throw new MediaUploadException(
    message: 'Upload failed',
    modelType: 'post',
    modelId: 42,
    collection: 'gallery',
);

// Có context() method cho logging
```

---

## API Routes

### Endpoints

```
POST   /api/v1/media/{modelType}/{modelId}/{collection}    → Upload
GET    /api/v1/media/{modelType}/{modelId}                 → List media
DELETE /api/v1/media/{mediaId}                             → Delete
```

#### 1. Upload Media

```http
POST /api/v1/media/post/42/gallery

Authorization: Bearer {TOKEN}
Content-Type: multipart/form-data

file: (binary)
```

**Success (201):**

```json
{
  "success": true,
  "message": "Upload thành công",
  "data": { ... }  // MediaResource
}
```

**Validation Error (422):**

```json
{
    "success": false,
    "message": "File không hợp lệ",
    "errors": {
        "file": ["Kích thước file vượt quá 10MB"]
    }
}
```

**Unauthorized (403):**

```json
{
    "success": false,
    "message": "Bạn không có quyền thực hiện hành động này"
}
```

#### 2. List Media

```http
GET /api/v1/media/post/42

Authorization: Bearer {TOKEN}
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "thumbnail": { ... },          // Single file (or null)
    "gallery": [ {...}, {...} ],   // Multiple files
    "documents": []                // Empty array
  }
}
```

#### 3. Delete Media

```http
DELETE /api/v1/media/12

Authorization: Bearer {TOKEN}
```

**Success (200):**

```json
{
    "success": true,
    "message": "Xóa thành công"
}
```

---

## Collection Types

### avatar

- **Type**: Single file
- **Mimes**: jpg, jpeg, png, webp
- **Max**: 10MB
- **Behavior**: Auto-replace (xóa cũ khi upload mới)
- **Models**: User, Post, Product

### thumbnail

- **Type**: Single file
- **Mimes**: jpg, jpeg, png, webp
- **Max**: 10MB
- **Models**: Post, Product

### gallery

- **Type**: Multiple files
- **Mimes**: jpg, jpeg, png, webp, mp4
- **Max**: 10MB/file
- **Models**: Post, Product

### documents

- **Type**: Multiple files
- **Mimes**: pdf
- **Max**: 10MB/file
- **Models**: User, Post, Product

---

## Examples

### Example 1: Add New Model (Category)

Chỉ 2 bước:

**Step 1: Add to `config/media.php`**

```php
'category' => [
    'class' => \App\Models\Category::class,
    'table' => 'categories',
    'owner_field' => null,  // Admin only
    'collections' => ['avatar'],
],
```

**Step 2: Add trait to model**

```php
class Category extends Model {
    use HasMediaCollections;
}
```

Done ✓ Bây giờ có thể upload:

```
POST /api/v1/media/category/5/avatar
```

### Example 2: Upload User Avatar

```bash
curl -X POST "http://localhost:8000/api/v1/media/user/1/avatar" \
  -H "Authorization: Bearer {TOKEN}" \
  -F "file=@./avatar.jpg"
```

Response:

```json
{
    "success": true,
    "data": {
        "id": 1,
        "url": "https://res.cloudinary.com/.../user/1/avatar/avatar.jpg",
        "collection": "avatar"
    }
}
```

### Example 3: Get All Media of Post

```bash
curl -X GET "http://localhost:8000/api/v1/media/post/42" \
  -H "Authorization: Bearer {TOKEN}"
```

Response:

```json
{
    "success": true,
    "data": {
        "thumbnail": { "id": 5, "url": "...", "collection": "thumbnail" },
        "gallery": [
            { "id": 6, "url": "...", "collection": "gallery" },
            { "id": 7, "url": "...", "collection": "gallery" }
        ],
        "documents": []
    }
}
```

### Example 4: Delete Media

```bash
curl -X DELETE "http://localhost:8000/api/v1/media/6" \
  -H "Authorization: Bearer {TOKEN}"
```

---

## Cloudinary Path Structure

Tự động tạo path theo cấu trúc:

```
{model_type}/{model_id}/{collection}/{filename}

Examples:
  user/1/avatar/profile.jpg
  user/1/documents/resume.pdf
  post/42/thumbnail/cover.png
  post/42/gallery/photo_1.jpg
  post/42/gallery/video.mp4
  product/15/avatar/main.jpg
  product/15/documents/spec.pdf
```

---

## Logging & Monitoring

Tất cả upload/delete operations được log:

```
[2026-02-27 12:30:45] local.INFO: Media uploaded successfully
{"model":"post","model_id":42,"collection":"gallery","media_id":12}

[2026-02-27 12:31:15] local.ERROR: Media upload failed
{"model":"product","model_id":5,"collection":"documents","error":"..."}
```

---

## Testing

### Using Postman

Import `POSTMAN_COLLECTION.json` vào Postman:

1. Click **Import**
2. Select **POSTMAN_COLLECTION.json**
3. Set variables: `TOKEN`, `USER_ID`, `POST_ID`, `PRODUCT_ID`
4. Run requests

### Using cURL

Xem `POSTMAN_TESTS.sh` cho 10+ test cases.

---

## Troubleshooting

### "Model type 'xyz' không được tìm thấy trong config"

**Cause**: Model chưa được thêm vào `config/media.php`

**Fix**: Thêm model vào config + add trait

### "Collection 'xyz' không hợp lệ"

**Cause**: Collection không được định nghĩa trong `config/media.php`

**Fix**: Thêm collection vào `collection_rules`

### "Cloudinary upload failed"

**Cause**: Credentials sai hoặc không có internet

**Fix**:

- Check `.env` variables
- Test Cloudinary credentials
- Check logs: `storage/logs/`

### "Bạn không có quyền"

**Cause**: User không owns model

**Fix**:

- Login as model owner
- Hoặc login as admin (super-admin role)

---

## Best Practices

1. **Always add trait to new models**

    ```php
    use HasMediaCollections;
    ```

2. **Define owner_field correctly**
    - User → `id`
    - Post → `user_id` (Post belongs to User)
    - Admin content → `null` (admin only)

3. **Use MediaService methods, not Spatie directly**

    ```php
    // ✓ Good
    $media = $mediaService->upload($model, 'gallery', $file);

    // ✗ Bad - ignore Spatie methods
    $media = $model->addMedia($file)->toMediaCollection('gallery');
    ```

4. **Check collection type before operations**

    ```php
    if (MediaCollectionConfig::isSingleFile('avatar')) {
        // Single file - use syncSingleFile
    }
    ```

5. **Always hash Cloudinary credentials**

    ```dotenv
    # ✓ Use environment variables
    CLOUDINARY_API_SECRET=xxx

    # ✗ Never hardcode
    // 'secret' => 'xxx',
    ```

---

## Summary

| Aspect                  | Solution                                       |
| ----------------------- | ---------------------------------------------- |
| Support multiple models | Config-driven ModelResolver                    |
| No code per-model       | HasMediaCollections trait (1 line!)            |
| Collection rules        | MediaCollectionConfig (single source of truth) |
| Cloudinary paths        | CloudinaryPathGenerator                        |
| Authorization           | MediaPolicy (ownership check)                  |
| Error handling          | MediaUploadException + rollback                |
| No local storage        | Cloudinary-only disk                           |
| Single controller       | MediaController (polymorphic routes)           |

---

## File Structure

```
app/
├── Exceptions/
│   └── MediaUploadException.php
├── Http/
│   ├── Controllers/Api/V1/
│   │   └── MediaController.php
│   ├── Requests/Media/
│   │   └── UploadMediaRequest.php
│   └── Resources/
│       └── MediaResource.php
├── Media/
│   ├── CloudinaryPathGenerator.php
│   └── MediaCollectionConfig.php
├── Models/
│   ├── User.php (+ HasMediaCollections)
│   ├── Post.php (+ HasMediaCollections)
│   └── Product.php (+ HasMediaCollections)
├── Policies/
│   └── MediaPolicy.php
├── Services/
│   ├── MediaService.php
│   └── ModelResolver.php
├── Traits/
│   └── HasMediaCollections.php
└── Providers/
    └── AuthServiceProvider.php

config/
├── media.php
├── media-library.php
└── filesystems.php
```

---

**Created**: 2026-02-27  
**Version**: 1.0  
**Laravel**: 12.x  
**PHP**: 8.2+
