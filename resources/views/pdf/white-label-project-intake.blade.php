<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #0D1B2A; }
    h1 { font-size: 20px; color: #0A6E6E; margin-bottom: 0; }
    .subtitle { color: #666; margin-top: 2px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    td { padding: 6px 8px; vertical-align: top; border-bottom: 1px solid #eee; }
    .label { font-weight: bold; width: 180px; color: #0A6E6E; }
    .swatch { display: inline-block; width: 14px; height: 14px; border: 1px solid #ccc; margin-right: 4px; }
    .section-title { font-size: 14px; font-weight: bold; color: #0A6E6E; margin-top: 20px; margin-bottom: 6px; border-bottom: 2px solid #D4A017; padding-bottom: 4px; }
    .credential-box { background: #fff8e6; border: 1px solid #D4A017; padding: 10px; margin-top: 6px; }
</style>
</head>
<body>
    <h1>White-Label Project Commencement Brief</h1>
    <p class="subtitle">Generated {{ now()->format('F j, Y') }} — NaaraSim / Supreme Ideas Agency</p>

    <div class="section-title">Project</div>
    <table>
        <tr><td class="label">Desired brand name</td><td>{{ $intake->desired_brand_name }}</td></tr>
        <tr><td class="label">WhatsApp contact</td><td>{{ $intake->whatsapp_number }}</td></tr>
        <tr><td class="label">License instance</td><td>{{ $intake->instance->brand_name }} ({{ $intake->instance->slug }}) — {{ ucfirst($intake->instance->tier) }} tier</td></tr>
        <tr><td class="label">Status</td><td>{{ ucfirst(str_replace('_', ' ', $intake->status)) }}</td></tr>
    </table>

    <div class="section-title">Brand identity</div>
    <table>
        <tr>
            <td class="label">Brand colours</td>
            <td>
                <span class="swatch" style="background:{{ $intake->brand_primary_color }}"></span> {{ $intake->brand_primary_color }}
                &nbsp;&nbsp;
                <span class="swatch" style="background:{{ $intake->brand_accent_color }}"></span> {{ $intake->brand_accent_color }}
            </td>
        </tr>
        @if ($intake->logo_url)
            <tr><td class="label">Logo</td><td>{{ $intake->logo_url }}</td></tr>
        @elseif ($intake->logo_design_reference)
            <tr><td class="label">Logo design reference</td><td>{{ $intake->logo_design_reference }}</td></tr>
        @endif
        @if ($intake->banner_reference_url)
            <tr><td class="label">Banner reference</td><td>{{ $intake->banner_reference_url }}</td></tr>
        @endif
        @if ($intake->banner_design_request)
            <tr><td class="label">Banner design request</td><td>{{ $intake->banner_design_request }}</td></tr>
        @endif
    </table>

    <div class="section-title">Hosting</div>
    <table>
        <tr><td class="label">Hosting choice</td><td>{{ str_replace('_', ' ', ucfirst($intake->hosting_choice)) }}</td></tr>
    </table>
    @if ($intake->isSelfHosted())
        <div class="credential-box">
            <strong>Access credentials — confidential, internal use only</strong>
            <table>
                <tr><td class="label">Host / control panel</td><td>{{ $intake->hosting_host }}</td></tr>
                <tr><td class="label">Username</td><td>{{ $intake->hosting_username }}</td></tr>
                <tr><td class="label">Password</td><td>{{ $intake->hosting_password }}</td></tr>
                @if ($intake->hosting_notes)
                    <tr><td class="label">Access notes</td><td>{{ $intake->hosting_notes }}</td></tr>
                @endif
            </table>
        </div>
    @endif

    @if ($intake->additional_notes)
        <div class="section-title">Additional notes</div>
        <p>{{ $intake->additional_notes }}</p>
    @endif

    @if ($intake->deploy_days)
        <div class="section-title">Deploy timeline</div>
        <table>
            <tr><td class="label">Duration</td><td>{{ $intake->deploy_days }} day(s)</td></tr>
            <tr><td class="label">Started</td><td>{{ $intake->deploy_started_at?->format('F j, Y') }}</td></tr>
        </table>
    @endif
</body>
</html>
