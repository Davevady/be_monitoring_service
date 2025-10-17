<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\AppRule;
use App\Model\AlertTarget;
use Hyperf\HttpServer\Contract\{RequestInterface, ResponseInterface as HttpResponse};

class AppRuleController
{
    public function index(HttpResponse $response)
    {
        $rules = AppRule::all();
        return $response->json([
            'status' => 'success',
            'data' => $rules,
        ]);
    }

    public function store(RequestInterface $request, HttpResponse $response)
    {
        $payload = $request->all();

        // Validasi sederhana & normalisasi
        if (empty($payload['app_name']) || ! isset($payload['max_duration'])) {
            return $response->json(['status' => 'error', 'message' => 'app_name dan max_duration wajib diisi'], 422);
        }

        $data = [
            'app_name' => (string) $payload['app_name'],
            'max_duration' => (int) $payload['max_duration'],
            'is_active' => isset($payload['is_active']) ? (bool) $payload['is_active'] : true,
            'cooldown_minutes' => isset($payload['cooldown_minutes']) ? (int) $payload['cooldown_minutes'] : 5,
            'alert_channels' => isset($payload['alert_channels']) ? (array) $payload['alert_channels'] : [],
        ];

        $rule = AppRule::create($data);

        // Optional: kaitkan alert targets jika dikirim
        if (! empty($payload['alert_target_ids']) && is_array($payload['alert_target_ids'])) {
            $ids = array_map('intval', $payload['alert_target_ids']);
            $rule->alertTargets()->sync($ids);
            $rule->load(['alertTargets']);
        }

        return $response->json(['status' => 'success', 'data' => $rule]);
    }

    public function show(int $id, HttpResponse $response)
    {
        $rule = AppRule::find($id);
        if (!$rule) {
            return $response->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        return $response->json(['status' => 'success', 'data' => $rule]);
    }

    public function update(int $id, RequestInterface $request, HttpResponse $response)
    {
        $rule = AppRule::find($id);
        if (!$rule) {
            return $response->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $payload = $request->all();
        $data = [];
        if (isset($payload['app_name'])) $data['app_name'] = (string) $payload['app_name'];
        if (isset($payload['max_duration'])) $data['max_duration'] = (int) $payload['max_duration'];
        if (isset($payload['is_active'])) $data['is_active'] = (bool) $payload['is_active'];
        if (isset($payload['cooldown_minutes'])) $data['cooldown_minutes'] = (int) $payload['cooldown_minutes'];
        if (isset($payload['alert_channels'])) $data['alert_channels'] = (array) $payload['alert_channels'];

        if (! empty($data)) {
            $rule->update($data);
        }

        if (isset($payload['alert_target_ids']) && is_array($payload['alert_target_ids'])) {
            $ids = array_map('intval', $payload['alert_target_ids']);
            $rule->alertTargets()->sync($ids);
        }

        $rule->load(['alertTargets']);
        return $response->json(['status' => 'success', 'data' => $rule]);
    }

    public function destroy(int $id, HttpResponse $response)
    {
        $rule = AppRule::find($id);
        if (!$rule) {
            return $response->json(['status' => 'error', 'message' => 'Not found'], 404);
        }

        $rule->delete();

        return $response->json(['status' => 'success', 'message' => 'Deleted']);
    }
}
