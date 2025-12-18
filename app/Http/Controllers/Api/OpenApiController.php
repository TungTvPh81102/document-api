<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Document API",
 *      description="API documentation for Document Management System",
 *      @OA\Contact(
 *          email="admin@example.com"
 *      )
 * )
 *
 * @OA\Server(
 *      url="http://localhost:8000",
 *      description="Document API Server (Local)"
 * )
 *
 * @OA\SecurityScheme(
 *      type="http",
 *      description="Bearer token security",
 *      name="Token based security",
 *      in="header",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 *      securityScheme="bearerAuth",
 * )
 */
class OpenApiController
{
    // OpenAPI/Swagger configuration file
}
