<?php

namespace App\Requests\V1;

use App\Constants\SupplierIntegrationTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_supplier_integrations');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'integration_type' => ['required', 'string', Rule::in(SupplierIntegrationTypes::all())],
            'name' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'configuration' => 'required|array',
            'configuration.api_endpoint' => 'required_if:integration_type,api|nullable|url|max:500',
            'configuration.rate_limit' => 'sometimes|integer|min:1|max:10000',
            'configuration.timeout' => 'sometimes|integer|min:5|max:300',
            'configuration.format' => 'sometimes|string|in:json,xml,csv',
            'configuration.webhook_url' => 'required_if:integration_type,webhook|nullable|url|max:500',
            'configuration.email_address' => 'required_if:integration_type,email|nullable|email|max:255',
            'configuration.ftp_host' => 'required_if:integration_type,ftp|nullable|string|max:255',
            'configuration.ftp_port' => 'sometimes|integer|min:1|max:65535',
            'authentication' => 'required|array',
            'authentication.api_key' => 'nullable|string|max:500',
            'authentication.api_secret' => 'nullable|string|max:500',
            'authentication.webhook_secret' => 'nullable|string|max:500',
            'authentication.ftp_username' => 'nullable|string|max:255',
            'authentication.ftp_password' => 'nullable|string|max:255',
            'sync_frequency_minutes' => 'sometimes|integer|min:1|max:10080', // Max 1 week
            'auto_retry_enabled' => 'sometimes|boolean',
            'max_retry_attempts' => 'sometimes|integer|min:1|max:10',
            'webhook_events' => 'sometimes|array',
            'webhook_events.*' => 'string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier is required.',
            'supplier_id.exists' => 'The selected supplier does not exist.',
            'integration_type.required' => 'Integration type is required.',
            'integration_type.in' => 'Invalid integration type. Must be one of: ' . implode(', ', SupplierIntegrationTypes::all()) . '.',
            'name.required' => 'Integration name is required.',
            'name.max' => 'Integration name cannot exceed 255 characters.',
            'configuration.required' => 'Configuration is required.',
            'configuration.api_endpoint.required_if' => 'API endpoint is required for API integrations.',
            'configuration.api_endpoint.url' => 'API endpoint must be a valid URL.',
            'configuration.webhook_url.required_if' => 'Webhook URL is required for webhook integrations.',
            'configuration.webhook_url.url' => 'Webhook URL must be a valid URL.',
            'configuration.email_address.required_if' => 'Email address is required for email integrations.',
            'configuration.email_address.email' => 'Email address must be valid.',
            'configuration.ftp_host.required_if' => 'FTP host is required for FTP integrations.',
            'configuration.rate_limit.min' => 'Rate limit must be at least 1 request.',
            'configuration.rate_limit.max' => 'Rate limit cannot exceed 10,000 requests.',
            'configuration.timeout.min' => 'Timeout must be at least 5 seconds.',
            'configuration.timeout.max' => 'Timeout cannot exceed 300 seconds.',
            'configuration.format.in' => 'Format must be one of: json, xml, csv.',
            'configuration.ftp_port.min' => 'FTP port must be at least 1.',
            'configuration.ftp_port.max' => 'FTP port cannot exceed 65535.',
            'authentication.required' => 'Authentication configuration is required.',
            'authentication.api_key' => 'API key for authentication.',
            'authentication.api_secret' => 'API secret for authentication.',
            'authentication.webhook_secret' => 'Webhook secret for signature validation.',
            'authentication.ftp_username' => 'FTP username for authentication.',
            'authentication.ftp_password' => 'FTP password for authentication.',
            'sync_frequency_minutes.min' => 'Sync frequency must be at least 1 minute.',
            'sync_frequency_minutes.max' => 'Sync frequency cannot exceed 1 week (10080 minutes).',
            'max_retry_attempts.min' => 'Maximum retry attempts must be at least 1.',
            'max_retry_attempts.max' => 'Maximum retry attempts cannot exceed 10.',
            'webhook_events.*.max' => 'Each webhook event name cannot exceed 100 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'auto_retry_enabled' => $this->boolean('auto_retry_enabled', true),
        ]);

        // Set defaults
        if (!$this->has('sync_frequency_minutes')) {
            $this->merge(['sync_frequency_minutes' => 60]);
        }

        if (!$this->has('max_retry_attempts')) {
            $this->merge(['max_retry_attempts' => 3]);
        }

        // Clean up configuration based on integration type
        if ($this->has('configuration')) {
            $config = $this->input('configuration');
            $integrationType = $this->input('integration_type');

            // Remove irrelevant configuration fields based on integration type
            switch ($integrationType) {
                case 'api':
                    unset($config['webhook_url'], $config['email_address'], $config['ftp_host'], $config['ftp_port']);
                    break;
                case 'webhook':
                    unset($config['api_endpoint'], $config['email_address'], $config['ftp_host'], $config['ftp_port']);
                    break;
                case 'email':
                    unset($config['api_endpoint'], $config['webhook_url'], $config['ftp_host'], $config['ftp_port']);
                    break;
                case 'ftp':
                    unset($config['api_endpoint'], $config['webhook_url'], $config['email_address']);
                    break;
            }

            $this->merge(['configuration' => $config]);
        }

        // Clean up authentication based on integration type
        if ($this->has('authentication')) {
            $auth = $this->input('authentication');
            $integrationType = $this->input('integration_type');

            // Remove irrelevant authentication fields based on integration type
            switch ($integrationType) {
                case 'api':
                    unset($auth['webhook_secret'], $auth['ftp_username'], $auth['ftp_password']);
                    break;
                case 'webhook':
                    unset($auth['api_key'], $auth['api_secret'], $auth['ftp_username'], $auth['ftp_password']);
                    break;
                case 'email':
                    unset($auth['api_key'], $auth['api_secret'], $auth['webhook_secret'], $auth['ftp_username'], $auth['ftp_password']);
                    break;
                case 'ftp':
                    unset($auth['api_key'], $auth['api_secret'], $auth['webhook_secret']);
                    break;
            }

            $this->merge(['authentication' => $auth]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $supplierId = $this->input('supplier_id');
            $integrationType = $this->input('integration_type');

            if ($supplierId && $integrationType) {
                // Check if supplier already has an active integration
                $existingIntegration = \App\Models\SupplierIntegration::where('supplier_id', $supplierId)
                    ->where('is_active', true)
                    ->first();

                if ($existingIntegration && $this->boolean('is_active', true)) {
                    $validator->errors()->add('is_active', 'Supplier already has an active integration. Only one integration can be active per supplier.');
                }

                // Validate configuration completeness based on integration type
                $this->validateConfigurationForType($validator, $integrationType);
            }
        });
    }

    private function validateConfigurationForType($validator, string $integrationType): void
    {
        $config = $this->input('configuration', []);
        $auth = $this->input('authentication', []);

        switch ($integrationType) {
            case 'api':
                if (empty($config['api_endpoint'])) {
                    $validator->errors()->add('configuration.api_endpoint', 'API endpoint is required for API integrations.');
                }
                if (empty($auth['api_key'])) {
                    $validator->errors()->add('authentication.api_key', 'API key is required for API integrations.');
                }
                break;

            case 'webhook':
                if (empty($config['webhook_url'])) {
                    $validator->errors()->add('configuration.webhook_url', 'Webhook URL is required for webhook integrations.');
                }
                break;

            case 'email':
                if (empty($config['email_address'])) {
                    $validator->errors()->add('configuration.email_address', 'Email address is required for email integrations.');
                }
                break;

            case 'ftp':
                if (empty($config['ftp_host'])) {
                    $validator->errors()->add('configuration.ftp_host', 'FTP host is required for FTP integrations.');
                }
                if (empty($auth['ftp_username'])) {
                    $validator->errors()->add('authentication.ftp_username', 'FTP username is required for FTP integrations.');
                }
                break;
        }
    }
}
