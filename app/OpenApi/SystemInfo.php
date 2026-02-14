<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="System API Documentation",
 *     version="1.0.0",
 *     description="API documentation for System Console — Users, Roles, Permissions, Auth",
 *     @OA\Contact(
 *         name="API Support",
 *         email="support@example.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/",
 *     description="Local Development"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="apiKey",
 *     name="Authorization",
 *     in="header",
 *     description="Enter token in format: Bearer {token}"
 * )
 *
 * @OA\Schema(
 *   schema="Role",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="Admin"),
 *   @OA\Property(property="slug", type="string", example="admin"),
 *   @OA\Property(property="description", type="string", example="Administrator role"),
 *   @OA\Property(property="is_system", type="boolean", example=false),
 *   @OA\Property(property="level", type="integer", example=1),
 *   @OA\Property(property="enabled", type="boolean", example=true),
 *   @OA\Property(property="created_by", type="string", nullable=true),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *   schema="Permission",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="View Users"),
 *   @OA\Property(property="slug", type="string", example="view-users"),
 *   @OA\Property(property="action", type="string", example="view"),
 *   @OA\Property(property="resource", type="string", example="users"),
 *   @OA\Property(property="description", type="string", example="Permission to view user list"),
 *   @OA\Property(property="is_system", type="boolean", example=false),
 *   @OA\Property(property="created_by", type="string", nullable=true),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class SystemInfo
{
}
