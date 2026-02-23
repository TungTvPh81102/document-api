<?php

namespace App\OpenApi\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *   schema="Site",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="Main Site"),
 *   @OA\Property(property="code", type="string", example="SITE001"),
 *   @OA\Property(property="slug", type="string", example="main-site"),
 *   @OA\Property(property="status", type="string", example="active"),
 *   @OA\Property(property="location", type="string", nullable=true),
 *   @OA\Property(property="timezone", type="string", example="UTC"),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *   schema="Plant",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="site_id", type="integer", example=1),
 *   @OA\Property(property="plant_code", type="string", example="PLT001"),
 *   @OA\Property(property="plant_name", type="string", example="Assembly Plant"),
 *   @OA\Property(property="plant_slug", type="string", example="assembly-plant"),
 *   @OA\Property(property="status", type="string", example="active"),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *   schema="SubSite",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="plant_id", type="integer", example=1),
 *   @OA\Property(property="sub_site_code", type="string", example="SS001"),
 *   @OA\Property(property="sub_site_name", type="string", example="Floor A"),
 *   @OA\Property(property="sub_site_slug", type="string", example="floor-a"),
 *   @OA\Property(property="status", type="string", example="active"),
 *   @OA\Property(property="created_at", type="string", format="date-time"),
 *   @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *   schema="SiteResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="data", ref="#/components/schemas/Site")
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="SiteListResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Site")
 *       ),
 *       @OA\Property(property="meta", type="object",
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page", type="integer", example=15),
 *         @OA\Property(property="total", type="integer", example=10)
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="PlantResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="data", ref="#/components/schemas/Plant")
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="PlantListResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Plant")
 *       ),
 *       @OA\Property(property="meta", type="object",
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page", type="integer", example=15),
 *         @OA\Property(property="total", type="integer", example=10)
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="SubSiteResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(property="data", ref="#/components/schemas/SubSite")
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="SubSiteListResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ApiResponse"),
 *     @OA\Schema(
 *       @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/SubSite")
 *       ),
 *       @OA\Property(property="meta", type="object",
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page", type="integer", example=15),
 *         @OA\Property(property="total", type="integer", example=10)
 *       )
 *     )
 *   }
 * )
 *
 * @OA\Schema(
 *   schema="ServiceCategory",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="code", type="string", example="IT"),
 *   @OA\Property(property="name", type="string", example="IT Services"),
 *   @OA\Property(property="is_active", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *   schema="Service",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="svc_code", type="string", example="S20260212002"),
 *   @OA\Property(property="title", type="string", example="WiMES System Upgrade"),
 *   @OA\Property(property="status", type="string", example="active"),
 *   @OA\Property(property="current_form", type="object",
 *     @OA\Property(property="version_no", type="integer", example=1),
 *     @OA\Property(property="fields", type="array", @OA\Items(ref="#/components/schemas/FormField"))
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="FormField",
 *   type="object",
 *   @OA\Property(property="field_key", type="string", example="plant"),
 *   @OA\Property(property="label", type="string", example="Plant Location"),
 *   @OA\Property(property="field_type", type="string", example="single_choice"),
 *   @OA\Property(property="is_required", type="boolean", example=true),
 *   @OA\Property(property="options", type="array", @OA\Items(type="string", example="P1")),
 *   @OA\Property(property="validation", type="object"),
 *   @OA\Property(property="visibility_rules", type="object")
 * )
 *
 * @OA\Schema(
 *   schema="ServiceRequest",
 *   type="object",
 *   @OA\Property(property="request_no", type="string", example="REQ-X8Z2"),
 *   @OA\Property(property="status", type="string", example="submitted"),
 *   @OA\Property(property="submitted_at", type="string", format="date-time"),
 *   @OA\Property(property="service", type="object",
 *     @OA\Property(property="svc_code", type="string", example="S20260212002"),
 *     @OA\Property(property="title", type="string", example="WiMES System Upgrade")
 *   ),
 *   @OA\Property(property="answers", type="object"),
 *   @OA\Property(property="attachments", type="array", @OA\Items(type="object"))
 * )
 */
class CoreSchemas {}
