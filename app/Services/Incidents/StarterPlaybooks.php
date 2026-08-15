<?php

namespace App\Services\Incidents;

use App\Models\IncidentPlaybook;
use App\Models\PlaybookVersion;

class StarterPlaybooks
{
    public function ensure(int $organizationId): void
    {
        if (IncidentPlaybook::withoutGlobalScopes()->where('organization_id', $organizationId)->exists()) {
            return;
        }

        foreach ($this->templates() as $template) {
            $playbook = IncidentPlaybook::withoutGlobalScopes()->create([
                'organization_id' => $organizationId, 'title' => $template['title'],
                'scope_type' => 'organization', 'current_version' => 1, 'is_template' => true,
            ]);
            PlaybookVersion::withoutGlobalScopes()->create([
                'organization_id' => $organizationId, 'incident_playbook_id' => $playbook->id,
                'version' => 1, 'steps' => $template['steps'],
                'authorized_roles' => ['admin', 'operator'], 'created_at' => now(),
            ]);
        }
    }

    private function templates(): array
    {
        return [
            ['title' => 'PLC unresponsive or suspected lockout', 'steps' => [
                ['title' => 'Protect the process', 'description' => 'Confirm process safety, notify the shift lead, and use approved manual control only if required.', 'role' => 'operator'],
                ['title' => 'Preserve evidence', 'description' => 'Record alarms, timestamps, HMI messages, and failed access attempts. Do not factory-reset the controller.', 'role' => 'operator'],
                ['title' => 'Contain access', 'description' => 'Ask the network owner to isolate remote access while preserving necessary local control paths.', 'role' => 'admin'],
                ['title' => 'Recover deliberately', 'description' => 'Validate the known-good configuration and credentials before reconnecting the device.', 'role' => 'admin'],
            ]],
            ['title' => 'Confirmed internet exposure - immediate isolation', 'steps' => [
                ['title' => 'Validate the finding', 'description' => 'Confirm the public address and service without logging into the device from an untrusted network.', 'role' => 'admin'],
                ['title' => 'Remove public reachability', 'description' => 'Disable the NAT, port-forward, or remote-access rule using the approved network change process.', 'role' => 'admin'],
                ['title' => 'Check device integrity', 'description' => 'Compare IP, firmware, configuration, users, and recent alarms with the accepted baseline.', 'role' => 'operator'],
                ['title' => 'Verify containment', 'description' => 'Run a new exposure check and keep the incident open until the device is no longer reachable.', 'role' => 'admin'],
            ]],
            ['title' => 'Manual fallback operation initiation', 'steps' => [
                ['title' => 'Authorize manual control', 'description' => 'The shift lead confirms the named operator is current and authorized for this site or asset.', 'role' => 'admin'],
                ['title' => 'Establish communications', 'description' => 'Set a check-in interval and a single coordinator for field and control-room actions.', 'role' => 'operator'],
                ['title' => 'Operate within safe limits', 'description' => 'Follow the site procedure, record every valve, pump, and setpoint action with time and operator.', 'role' => 'operator'],
                ['title' => 'Controlled return', 'description' => 'Verify automation health and reconcile manual positions before returning to automatic control.', 'role' => 'admin'],
            ]],
        ];
    }
}
