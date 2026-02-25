<?php

namespace App\OpenApi\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *   schema="ApiResponse",
 *   type="object",
 *   required={"success","message","code","timestamp"},
 *   @OA\Property(property="success", type="boolean", example=true),
 *   @OA\Property(property="message", type="string", example="Operation successful"),
 *   @OA\Property(property="code", type="integer", example=200),
 *   @OA\Property(property="data"),
 *   @OA\Property(property="errors", type="object", nullable=true, example=null),
 *   @OA\Property(property="correlation_id", type="string", nullable=true, example="01JDN3X6H1V5C0Y8K9P2R4M7TQ"),
 *   @OA\Property(property="links", type="object", nullable=true,
 *     @OA\Property(property="self", type="string", example="https://api.example.com/api/users/123"),
 *     @OA\Property(property="update", type="string", example="https://api.example.com/api/users/123"),
 *     @OA\Property(property="delete", type="string", example="https://api.example.com/api/users/123")
 *   ),
 *   @OA\Property(property="meta", type="object", nullable=true),
 *   @OA\Property(property="debug", type="object", nullable=true),
 *   @OA\Property(property="timestamp", type="string", format="date-time"),
 *   @OA\Property(property="request_id", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *   schema="User",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=123),
 *   @OA\Property(property="code", type="string", example="USR-2024-000123"),
 *   @OA\Property(property="name", type="string", example="Nguyen Van A"),
 *   @OA\Property(property="email", type="string", example="a.nguyen@example.com"),
 *   @OA\Property(property="is_active", type="boolean", example=true),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *   schema="UserResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="data", ref="#/components/schemas/User")
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="UserListResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/User")
 *       ),
 *       @OA\Property(property="meta", type="object",
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page", type="integer", example=15),
 *         @OA\Property(property="total", type="integer", example=150)
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="ErrorResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="success", type="boolean", example=false),
 *       @OA\Property(property="message", type="string", example="Validation failed"),
 *       @OA\Property(property="code", type="integer", example=422),
 *       @OA\Property(property="errors", type="object",
 *         @OA\Property(property="email", type="array",
 *           @OA\Items(type="string", example="The email has already been taken.")
 *         )
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="UserCollectionResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/User")
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="BulkOperationResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="operation", type="string", example="delete"),
 *       @OA\Property(property="successful", type="integer", example=3),
 *       @OA\Property(property="failed", type="integer", example=1),
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(type="object",
 *           @OA\Property(property="id", type="integer", example=10),
 *           @OA\Property(property="status", type="string", example="success"),
 *           @OA\Property(property="error", type="string", nullable=true, example=null)
 *         )
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="DeleteResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="data", type="object",
 *         @OA\Property(property="deleted", type="boolean", example=true)
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="UserStatsResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="data", type="object",
 *         @OA\Property(property="total_users", type="integer", example=120),
 *         @OA\Property(property="active_users", type="integer", example=110),
 *         @OA\Property(property="new_today", type="integer", example=5)
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="CurrentUserResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(
 *         property="data",
 *         nullable=true,
 *         oneOf={
 *           @OA\Schema(ref="#/components/schemas/User")
 *         }
 *       )
 *     )
 *   }
 * )
 */
class UserSchemas {}
