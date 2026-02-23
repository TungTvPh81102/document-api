<?php

namespace App\Services;

use App\Models\DynamicService\FormTemplate;
use App\Models\DynamicService\FormTemplateVersion;
use App\Models\DynamicService\Service;
use App\Models\DynamicService\ServiceCategory;
use App\Models\DynamicService\ServiceTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceManagementService
{
    public function __construct(
        private FormEngineService $formEngine
    ) {}

    /**
     * Create a new service category.
     */
    public function createCategory(array $data): ServiceCategory
    {
        return ServiceCategory::query()->create($data);
    }

    /**
     * Create a new service and initial form template version.
     */
    public function createServiceWithForm(array $canonicalData, int $businessCategoryId): Service
    {
        return DB::transaction(function () use ($canonicalData, $businessCategoryId) {
            // 1. Create Form Template
            $template = FormTemplate::query()->create([
                'code' => $canonicalData['service']['svcCode'] . '_TEMPLATE',
                'name' => 'Template for ' . ($canonicalData['service']['titles'][0]['title'] ?? 'Service'),
            ]);

            // 2. Create Service
            $service = Service::query()->create([
                'svc_code'             => $canonicalData['service']['svcCode'],
                'business_category_id' => $businessCategoryId,
                'form_template_id'     => $template->id,
                'status'               => 'active',
            ]);

            // 3. Create Translations
            foreach ($canonicalData['service']['titles'] as $titleItem) {
                ServiceTranslation::query()->create([
                    'service_id'  => $service->id,
                    'locale'      => $titleItem['locale'],
                    'title'       => $titleItem['title'],
                ]);
            }

            // 4. Create Form Template Version
            $this->createFormVersion($service, $canonicalData['form']['fields']);

            return $service->load(['translations', 'currentVersion.fields']);
        });
    }

    /**
     * Create a new version for a service's form template.
     */
    public function createFormVersion(Service $service, array $fieldsData): FormTemplateVersion
    {
        return DB::transaction(function () use ($service, $fieldsData) {
            $template = $service->formTemplate;
            $nextVersionNo = ($template->versions()->max('version_no') ?? 0) + 1;

            $version = FormTemplateVersion::query()->create([
                'form_template_id' => $template->id,
                'version_no'       => $nextVersionNo,
                'schema_json'      => $fieldsData,
                'status'           => 'published',
                'published_at'     => now(),
            ]);

            // Create Field definitions
            foreach ($fieldsData as $order => $fieldData) {
                $field = $version->fields()->create([
                    'field_key'            => $fieldData['fieldKey'] ?? Str::uuid(),
                    'label_default'        => $fieldData['labels']['default'] ?? 'Unnamed',
                    'field_type'           => $fieldData['fieldType'] ?? 'short_text',
                    'is_required'          => $fieldData['required'] ?? false,
                    'display_order'        => $order,
                    'options_json'         => $fieldData['options'] ?? null,
                    'validation_json'      => $fieldData['validation'] ?? null,
                    'visibility_rule_json' => $fieldData['visibility'] ?? null,
                ]);

                // Handle multi-language labels if present
                if (isset($fieldData['labels']) && is_array($fieldData['labels'])) {
                    foreach ($fieldData['labels'] as $locale => $label) {
                        if ($locale === 'default') continue;
                        $field->translations()->create([
                            'locale' => $locale,
                            'label'  => $label,
                        ]);
                    }
                }
            }

            // Update service to point to current version
            $service->update(['current_form_version_id' => $version->id]);

            return $version;
        });
    }
}
