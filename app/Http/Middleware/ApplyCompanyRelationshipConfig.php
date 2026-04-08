<?php

namespace App\Http\Middleware;

use App\Services\Master\RelationshipMasterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyCompanyRelationshipConfig
{
    public function __construct(
        private readonly RelationshipMasterService $relationshipMasterService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        if ($companyId > 0) {
            $this->relationshipMasterService->applyRuntimeConfig($companyId);
        }

        return $next($request);
    }
}