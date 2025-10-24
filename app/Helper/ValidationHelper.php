<?php

declare(strict_types=1);

namespace App\Helper;

use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Di\Annotation\Inject;

/**
 * Validation Helper
 * 
 * Provides validation functionality for requests
 */
class ValidationHelper
{
    /**
     * Validate request data using simple validation
     */
    public function validate(RequestInterface $request, array $rules, array $messages = []): array
    {
        $data = $request->all();
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $error = $this->validateField($field, $value, $rule, $data);
                if ($error) {
                    $errors[$field][] = $error;
                    break; // Stop at first error for this field
                }
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Validate individual field
     */
    private function validateField(string $field, $value, string $rule, array $data): ?string
    {
        switch ($rule) {
            case 'required':
                if (empty($value)) {
                    return "The {$field} field is required.";
                }
                break;
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "The {$field} field must be a valid email address.";
                }
                break;
            case 'string':
                if (!empty($value) && !is_string($value)) {
                    return "The {$field} field must be a string.";
                }
                break;
            case 'integer':
                if (!empty($value) && !is_numeric($value)) {
                    return "The {$field} field must be an integer.";
                }
                break;
            case 'boolean':
                if (!empty($value) && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false])) {
                    return "The {$field} field must be true or false.";
                }
                break;
            case 'array':
                if (!empty($value) && !is_array($value)) {
                    return "The {$field} field must be an array.";
                }
                break;
            case 'confirmed':
                $confirmationField = $field . '_confirmation';
                if ($value !== ($data[$confirmationField] ?? null)) {
                    return "The {$field} confirmation does not match.";
                }
                break;
            default:
                // Handle rules with parameters like 'max:100', 'min:6', etc.
                if (strpos($rule, ':') !== false) {
                    [$ruleName, $param] = explode(':', $rule, 2);
                    return $this->validateFieldWithParam($field, $value, $ruleName, $param, $data);
                }
                break;
        }

        return null;
    }

    /**
     * Validate field with parameter
     */
    private function validateFieldWithParam(string $field, $value, string $rule, string $param, array $data): ?string
    {
        switch ($rule) {
            case 'max':
                if (!empty($value) && strlen((string)$value) > (int)$param) {
                    return "The {$field} field must not exceed {$param} characters.";
                }
                break;
            case 'min':
                if (!empty($value) && strlen((string)$value) < (int)$param) {
                    return "The {$field} field must be at least {$param} characters.";
                }
                break;
            case 'size':
                if (!empty($value) && strlen((string)$value) !== (int)$param) {
                    return "The {$field} field must be exactly {$param} characters.";
                }
                break;
            case 'unique':
                // Simple unique check - in real implementation, you'd check database
                // For now, just return null (no error)
                break;
            case 'exists':
                // Simple exists check - in real implementation, you'd check database
                // For now, just return null (no error)
                break;
            case 'regex':
                if (!empty($value) && !preg_match($param, (string)$value)) {
                    return "The {$field} field format is invalid.";
                }
                break;
        }

        return null;
    }

    /**
     * Get validation rules for login
     */
    public function getLoginRules(): array
    {
        return [
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ];
    }

    /**
     * Get validation messages for login
     */
    public function getLoginMessages(): array
    {
        return [
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.max' => 'Email must not exceed 150 characters',
            'password.required' => 'Password is required',
            'password.string' => 'Password must be a string',
            'password.min' => 'Password must be at least 6 characters',
            'password.max' => 'Password must not exceed 255 characters',
        ];
    }

    /**
     * Get validation rules for forgot password
     */
    public function getForgotPasswordRules(): array
    {
        return [
            'email' => ['required', 'email', 'max:150'],
        ];
    }

    /**
     * Get validation messages for forgot password
     */
    public function getForgotPasswordMessages(): array
    {
        return [
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.max' => 'Email must not exceed 150 characters',
        ];
    }

    /**
     * Get validation rules for reset password
     */
    public function getResetPasswordRules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * Get validation messages for reset password
     */
    public function getResetPasswordMessages(): array
    {
        return [
            'token.required' => 'Reset token is required',
            'token.string' => 'Reset token must be a string',
            'token.size' => 'Reset token must be exactly 64 characters',
            'password.required' => 'Password is required',
            'password.string' => 'Password must be a string',
            'password.min' => 'Password must be at least 8 characters',
            'password.max' => 'Password must not exceed 255 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'password_confirmation.required' => 'Password confirmation is required',
            'password_confirmation.string' => 'Password confirmation must be a string',
            'password_confirmation.min' => 'Password confirmation must be at least 8 characters',
            'password_confirmation.max' => 'Password confirmation must not exceed 255 characters',
        ];
    }

    /**
     * Get validation rules for update profile
     */
    public function getUpdateProfileRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:255', 'url'],
        ];
    }

    /**
     * Get validation messages for update profile
     */
    public function getUpdateProfileMessages(): array
    {
        return [
            'name.string' => 'Name must be a string',
            'name.max' => 'Name must not exceed 100 characters',
            'phone.string' => 'Phone must be a string',
            'phone.max' => 'Phone must not exceed 20 characters',
            'phone.regex' => 'Phone must be a valid phone number',
            'avatar.string' => 'Avatar must be a string',
            'avatar.max' => 'Avatar must not exceed 255 characters',
            'avatar.url' => 'Avatar must be a valid URL',
        ];
    }

    /**
     * Get validation rules for change password
     */
    public function getChangePasswordRules(): array
    {
        return [
            'current_password' => ['required', 'string', 'min:6', 'max:255'],
            'new_password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'new_password_confirmation' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * Get validation messages for change password
     */
    public function getChangePasswordMessages(): array
    {
        return [
            'current_password.required' => 'Current password is required',
            'current_password.string' => 'Current password must be a string',
            'current_password.min' => 'Current password must be at least 6 characters',
            'current_password.max' => 'Current password must not exceed 255 characters',
            'new_password.required' => 'New password is required',
            'new_password.string' => 'New password must be a string',
            'new_password.min' => 'New password must be at least 8 characters',
            'new_password.max' => 'New password must not exceed 255 characters',
            'new_password.confirmed' => 'New password confirmation does not match',
            'new_password_confirmation.required' => 'New password confirmation is required',
            'new_password_confirmation.string' => 'New password confirmation must be a string',
            'new_password_confirmation.min' => 'New password confirmation must be at least 8 characters',
            'new_password_confirmation.max' => 'New password confirmation must not exceed 255 characters',
        ];
    }

    /**
     * Get validation rules for create role
     */
    public function getCreateRoleRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:roles,name', 'regex:/^[a-z_]+$/'],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get validation messages for create role
     */
    public function getCreateRoleMessages(): array
    {
        return [
            'name.required' => 'Role name is required',
            'name.string' => 'Role name must be a string',
            'name.max' => 'Role name must not exceed 50 characters',
            'name.unique' => 'Role name already exists',
            'name.regex' => 'Role name must contain only lowercase letters and underscores',
            'display_name.required' => 'Display name is required',
            'display_name.string' => 'Display name must be a string',
            'display_name.max' => 'Display name must not exceed 100 characters',
            'description.string' => 'Description must be a string',
            'description.max' => 'Description must not exceed 500 characters',
            'is_active.boolean' => 'Active status must be true or false',
        ];
    }

    /**
     * Get validation rules for update role
     */
    public function getUpdateRoleRules(int $roleId): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:50', 'unique:roles,name,' . $roleId, 'regex:/^[a-z_]+$/'],
            'display_name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get validation messages for update role
     */
    public function getUpdateRoleMessages(): array
    {
        return [
            'name.string' => 'Role name must be a string',
            'name.max' => 'Role name must not exceed 50 characters',
            'name.unique' => 'Role name already exists',
            'name.regex' => 'Role name must contain only lowercase letters and underscores',
            'display_name.string' => 'Display name must be a string',
            'display_name.max' => 'Display name must not exceed 100 characters',
            'description.string' => 'Description must be a string',
            'description.max' => 'Description must not exceed 500 characters',
            'is_active.boolean' => 'Active status must be true or false',
        ];
    }

    /**
     * Get validation rules for assign permissions
     */
    public function getAssignPermissionsRules(): array
    {
        return [
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * Get validation messages for assign permissions
     */
    public function getAssignPermissionsMessages(): array
    {
        return [
            'permission_ids.required' => 'Permission IDs are required',
            'permission_ids.array' => 'Permission IDs must be an array',
            'permission_ids.min' => 'At least one permission must be selected',
            'permission_ids.*.integer' => 'Each permission ID must be an integer',
            'permission_ids.*.exists' => 'One or more permission IDs do not exist',
        ];
    }

    /**
     * Get validation rules for create permission
     */
    public function getCreatePermissionRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:permissions,name', 'regex:/^[a-z._]+$/'],
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'group' => ['sometimes', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get validation messages for create permission
     */
    public function getCreatePermissionMessages(): array
    {
        return [
            'name.required' => 'Permission name is required',
            'name.string' => 'Permission name must be a string',
            'name.max' => 'Permission name must not exceed 100 characters',
            'name.unique' => 'Permission name already exists',
            'name.regex' => 'Permission name must contain only lowercase letters, dots, and underscores',
            'display_name.required' => 'Display name is required',
            'display_name.string' => 'Display name must be a string',
            'display_name.max' => 'Display name must not exceed 150 characters',
            'description.string' => 'Description must be a string',
            'description.max' => 'Description must not exceed 500 characters',
            'group.string' => 'Group must be a string',
            'group.max' => 'Group must not exceed 50 characters',
            'is_active.boolean' => 'Active status must be true or false',
        ];
    }

    /**
     * Get validation rules for update permission
     */
    public function getUpdatePermissionRules(int $permissionId): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100', 'unique:permissions,name,' . $permissionId, 'regex:/^[a-z._]+$/'],
            'display_name' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'group' => ['sometimes', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get validation messages for update permission
     */
    public function getUpdatePermissionMessages(): array
    {
        return [
            'name.string' => 'Permission name must be a string',
            'name.max' => 'Permission name must not exceed 100 characters',
            'name.unique' => 'Permission name already exists',
            'name.regex' => 'Permission name must contain only lowercase letters, dots, and underscores',
            'display_name.string' => 'Display name must be a string',
            'display_name.max' => 'Display name must not exceed 150 characters',
            'description.string' => 'Description must be a string',
            'description.max' => 'Description must not exceed 500 characters',
            'group.string' => 'Group must be a string',
            'group.max' => 'Group must not exceed 50 characters',
            'is_active.boolean' => 'Active status must be true or false',
        ];
    }
}
