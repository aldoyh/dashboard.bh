<?php

/**
 * Server Dashboard - Simplified System Information Display
 * This file contains functions to retrieve system information and display it in a server dashboard.
 * The functions include getting basic server info, CPU info, memory usage, disk usage, uptime, load average,
 * network interfaces, and process list.
 * 
 * @author Zxce3
 * @version 2.0
 */

class SystemInfo
{
    private static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function getBasicInfo()
    {
        return [
            'Hostname' => gethostname(),
            'OS' => php_uname('s') . ' ' . php_uname('r'),
            'PHP Version' => phpversion(),
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ];
    }

    public static function getCPUInfo()
    {
        if (!file_exists('/proc/cpuinfo') || !file_exists('/proc/stat')) return ['CPU Info' => 'Not available'];
        $cpu_info = file_get_contents('/proc/cpuinfo');
        preg_match('/model name\s+:\s+(.+)$/m', $cpu_info, $model);
        preg_match_all('/^processor\s+:\s+\d+$/m', $cpu_info, $cores);
        $stat_info = file_get_contents('/proc/stat');
        preg_match('/cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat_info, $cpu_usage);
        $total_time = array_sum(array_slice($cpu_usage, 1));
        $idle_time = $cpu_usage[4];
        $usage_percentage = round((($total_time - $idle_time) / $total_time) * 100, 2);
        return [
            'Model' => $model[1] ?? 'Unknown',
            'Cores' => count($cores[0]),
            'Active Cores' => count($cores[0]),
            'CPU Usage' => $usage_percentage . '%'
        ];
    }

    public static function getMemoryInfo()
    {
        if (!file_exists('/proc/meminfo')) return ['Memory Info' => 'Not available'];
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        $total = isset($total[1]) ? (int)$total[1] * 1024 : 0;
        $available = isset($available[1]) ? (int)$available[1] * 1024 : 0;
        $used = $total - $available;
        return [
            'Total' => self::formatBytes($total),
            'Used' => self::formatBytes($used),
            'Available' => self::formatBytes($available),
            'Usage' => round(($used / $total) * 100, 2) . '%'
        ];
    }

    public static function getDiskInfo()
    {
        $disks = [];
        $df = shell_exec('df -B1');
        if (!$df) return ['Disk Info' => 'Not available'];
        foreach (explode("\n", $df) as $line) {
            if (preg_match('/^\/dev\//', $line)) {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 6) {
                    $mount = $parts[5];
                    $disks[$mount] = [
                        'Total' => self::formatBytes((int)$parts[1]),
                        'Used' => self::formatBytes((int)$parts[2]),
                        'Available' => self::formatBytes((int)$parts[3]),
                        'Usage' => $parts[4]
                    ];
                }
            }
        }
        return $disks;
    }

    public static function getUptime()
    {
        if (!file_exists('/proc/uptime')) return 'Not available';
        $uptime = (int)file_get_contents('/proc/uptime');
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        return sprintf("%d days, %d hours, %d minutes", $days, $hours, $minutes);
    }

    public static function getLoadAverage()
    {
        $load = sys_getloadavg();
        return [
            '1min' => number_format($load[0], 2),
            '5min' => number_format($load[1], 2),
            '15min' => number_format($load[2], 2)
        ];
    }

    public static function getNetworkInfo()
    {
        $interfaces = [];
        $ifconfig = shell_exec('ifconfig -a');
        if (!$ifconfig) return ['Network Info' => 'Not available'];
        preg_match_all('/^(\S+): flags/m', $ifconfig, $matches);
        foreach ($matches[1] as $interface) {
            preg_match("/$interface:.*?inet (\d+\.\d+\.\d+\.\d+)/s", $ifconfig, $ip);
            preg_match("/$interface:.*?ether ([\da-f:]+)/s", $ifconfig, $mac);
            preg_match("/$interface:.*?RX packets.*?bytes (\d+)/s", $ifconfig, $rx);
            preg_match("/$interface:.*?TX packets.*?bytes (\d+)/s", $ifconfig, $tx);
            $interfaces[$interface] = [
                'IP Address' => $ip[1] ?? 'Not available',
                'MAC Address' => $mac[1] ?? 'Not available',
                'RX Data' => isset($rx[1]) ? self::formatBytes($rx[1]) : 'Not available',
                'TX Data' => isset($tx[1]) ? self::formatBytes($tx[1]) : 'Not available'
            ];
        }
        return $interfaces;
    }

    public static function getProcessList()
    {
        $processes = [];
        $ps = shell_exec('ps aux --sort=-%mem');
        if (!$ps) return ['Process List' => 'Not available'];
        $lines = explode("\n", $ps);
        array_shift($lines); // Remove header line
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $columns = preg_split('/\s+/', $line, 11);
            if (count($columns) >= 11) {
                $processes[] = [
                    'User' => $columns[0],
                    'PID' => $columns[1],
                    'CPU' => $columns[2],
                    'Memory' => $columns[3],
                    'Command' => $columns[10]
                ];
            }
        }
        return $processes;
    }
}

// API endpoint for AJAX updates
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'basic' => SystemInfo::getBasicInfo(),
        'cpu' => SystemInfo::getCPUInfo(),
        'memory' => SystemInfo::getMemoryInfo(),
        'disk' => SystemInfo::getDiskInfo(),
        'uptime' => SystemInfo::getUptime(),
        'load' => SystemInfo::getLoadAverage(),
        'network' => SystemInfo::getNetworkInfo(),
        'processes' => SystemInfo::getProcessList(),
        'timestamp' => date('Y-m-d H:i:s T')
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Dashboard - <?php echo gethostname(); ?></title>
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --card-bg: #f5f5f5;
            --border-color: #dddddd;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --text-color: #ffffff;
            --card-bg: #2d2d2d;
            --border-color: #404040;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            transition: background-color 0.3s;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .theme-toggle {
            background: none;
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .data-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .data-list li {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .data-list li:last-child {
            border-bottom: none;
        }

        .accordion-header {
            cursor: pointer;
            background: var(--card-bg);
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 5px;
            transition: background 0.3s;
        }

        .accordion-header:hover {
            background: var(--border-color);
        }

        .accordion-content {
            display: none;
            overflow: hidden;
        }

        .accordion-content.active {
            display: block;
        }

        @media (max-width: 600px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .process-list-container {
            max-height: 400px;
            overflow-y: auto;
            border-radius: 4px;
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) transparent;
        }

        .process-list-container::-webkit-scrollbar {
            width: 8px;
        }

        .process-list-container::-webkit-scrollbar-track {
            background: var(--card-bg);
            border-radius: 4px;
        }

        .process-list-container::-webkit-scrollbar-thumb {
            background-color: var(--border-color);
            border-radius: 4px;
        }

        .data-table {
            position: relative;
        }

        .data-table thead {
            position: sticky;
            top: 0;
            background-color: var(--card-bg);
            z-index: 1;
        }

        .data-table tbody tr:hover {
            background-color: var(--border-color);
        }

        .data-table th,
        .data-table td {
            padding: 10px;
            text-align: left;
        }

        .process-card {
            grid-column: 1 / -1 !important;
        }

        .badge {
            background: var(--border-color);
            border-radius: 5px;
            font-size: medium;
            padding: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Server Dashboard <span class="badge">V2</span></h1>
            <button class="theme-toggle" onclick="toggleTheme()">Toggle Theme</button>
        </div>
        <div class="grid" id="dashboard">
            <!-- Content will be loaded via JavaScript -->
        </div>
    </div>

    <script>
        function toggleTheme() {
            const theme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        }

        document.body.setAttribute('data-theme', localStorage.getItem('theme') || 'light');

        function toggleAccordion(event) {
            const content = event.target.nextElementSibling;
            content.classList.toggle('active');
        }

        function updateDashboard() {
            fetch('?api=1')
                .then(response => response.json())
                .then(data => {
                    const dashboard = document.getElementById('dashboard');
                    dashboard.innerHTML = `
                        <div class="card">
                            <h2>System Information</h2>
                            <ul class="data-list">
                                ${Object.entries(data.basic).map(([k,v]) => `
                                    <li><span>${k}</span><span>${v}</span></li>
                                `).join('')}
                                <li><span>Uptime</span><span>${data.uptime}</span></li>
                            </ul>
                        </div>

                        <div class="card">
                            <h2>CPU Information</h2>
                            <ul class="data-list">
                                ${Object.entries(data.cpu).map(([k,v]) => `
                                    <li><span>${k}</span><span>${v}</span></li>
                                `).join('')}
                            </ul>
                        </div>

                        <div class="card">
                            <h2>Memory Usage</h2>
                            <ul class="data-list">
                                ${Object.entries(data.memory).map(([k,v]) => `
                                    <li><span>${k}</span><span>${v}</span></li>
                                `).join('')}
                            </ul>
                        </div>

                        <div class="card">
                            <h2>Load Average</h2>
                            <ul class="data-list">
                                ${Object.entries(data.load).map(([k,v]) => `
                                    <li><span>${k}</span><span>${v}</span></li>
                                `).join('')}
                            </ul>
                        </div>

                        <div class="card">
                            <h2>Disk Usage</h2>
                            ${Object.entries(data.disk).map(([mount, info]) => `
                                <div class="accordion-header" onclick="toggleAccordion(event)">${mount}</div>
                                <div class="accordion-content">
                                    <ul class="data-list">
                                        ${Object.entries(info).map(([k,v]) => `
                                            <li><span>${k}</span><span>${v}</span></li>
                                        `).join('')}
                                    </ul>
                                </div>
                            `).join('')}
                        </div>

                        <div class="card">
                            <h2>Network Interfaces</h2>
                            ${Object.entries(data.network).map(([iface, info]) => `
                                <div class="accordion-header" onclick="toggleAccordion(event)">${iface}</div>
                                <div class="accordion-content">
                                    <ul class="data-list">
                                        ${Object.entries(info).map(([k,v]) => `
                                            <li><span>${k}</span><span>${v}</span></li>
                                        `).join('')}
                                    </ul>
                                </div>
                            `).join('')}
                        </div>
                        <div class="card process-card">
                            <h2>Process List</h2>
                            <div class="process-list-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>PID</th>
                                            <th>CPU</th>
                                            <th>Memory</th>
                                            <th>Command</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.processes.map(process => `
                                            <tr>
                                                <td>${process.User}</td>
                                                <td>${process.PID}</td>
                                                <td>${process.CPU}%</td>
                                                <td>${process.Memory}%</td>
                                                <td>${process.Command}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                })
                .catch(error => console.error('Error updating dashboard:', error));
        }

        updateDashboard();
        setInterval(updateDashboard, 30000); // Update every 30 seconds
    </script>
</body>

</html>
