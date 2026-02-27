<?php

return [
    /**
     * MODEL_REGISTRY — Định nghĩa mapping giữa models và collections
     * Cấu trúc:
     *   - name: Tên model (gốc)
     *   - table: Tên bảng database (để identify ownership)
     *   - route_prefix: Prefix route API cho model này
     *   - owner_field: Field để check ownership (thường là 'id' hoặc 'user_id')
     *   - collections: Danh sách collections model này hỗ trợ
     * 
     * Cách sử dụng:
     *   - Thêm model mới vào registry
     *   - Thêm use HasMediaCollections; vào Model class
     *   - KHÔNG cần tạo controller hay service thêm
     */
    'models' => [
        'user' => [
            'class'         => \App\Models\User::class,
            'table'         => 'users',
            'route_prefix'  => '/api/v1/users',
            'owner_field'   => 'id',
            'collections'   => ['avatar', 'documents'],
        ],
        // Thêm model mới ở đây khi cần
    ],

    /**
     * COLLECTION_RULES — Định nghĩa rules cho từng collection type
     * Áp dụng cho tất cả models (single source of truth)
     * 
     * Nếu sửa 1 rule ở đây → áp dụng toàn app
     */
    'collection_rules' => [
        'avatar' => [
            'type'      => 'single',        // singleFile hoặc multiple
            'mimes'     => ['jpg', 'jpeg', 'png', 'webp'],
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_size'  => 10 * 1024 * 1024, // 10MB in bytes
            'disk'      => 'cloudinary',
        ],
        'thumbnail' => [
            'type'      => 'single',
            'mimes'     => ['jpg', 'jpeg', 'png', 'webp'],
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_size'  => 10 * 1024 * 1024,
            'disk'      => 'cloudinary',
        ],
        'gallery' => [
            'type'      => 'multiple',
            'mimes'     => ['jpg', 'jpeg', 'png', 'webp', 'mp4'],
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'mp4'],
            'max_size'  => 10 * 1024 * 1024,
            'disk'      => 'cloudinary',
        ],
        'documents' => [
            'type'      => 'multiple',
            'mimes'     => ['pdf'],
            'extensions' => ['pdf'],
            'max_size'  => 10 * 1024 * 1024,
            'disk'      => 'cloudinary',
        ],
    ],

    /**
     * DEFAULT_DISK — Disk mặc định cho media storage
     */
    'default_disk' => env('MEDIA_DISK', 'cloudinary'),
];
