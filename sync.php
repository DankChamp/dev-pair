<?php
/**
 * DevPair Sync Backend
 * Upload alongside index.html on MilesWeb hosting.
 * Flat JSON storage — no database required.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('DATA_DIR', __DIR__ . '/devpair_data/');
define('PROJECTS_DIR', DATA_DIR . 'projects/');
define('TEAM_FILE', DATA_DIR . 'team.json');

foreach ([DATA_DIR, PROJECTS_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function read_json($path, $default = null) {
    if (!file_exists($path)) return $default;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : $default;
}

function write_json($path, $data) {
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function slug_id($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s ?: 'project-' . time();
}

function team_defaults() {
    return [
        'groqKey' => '',
        'members' => [],
        'projects' => [],
        'updatedAt' => 0,
    ];
}

function load_team() {
    return array_merge(team_defaults(), read_json(TEAM_FILE, team_defaults()));
}

function save_team($team) {
    $team['updatedAt'] = time() * 1000;
    write_json(TEAM_FILE, $team);
}

function project_path($id) {
    $id = preg_replace('/[^a-z0-9\-_]/', '', $id);
    return PROJECTS_DIR . $id . '.json';
}

function sanitize_role($role) {
    return in_array($role, ['dev1', 'dev2'], true) ? $role : 'dev1';
}

function sanitize_project($body) {
    return [
        'id' => preg_replace('/[^a-z0-9\-_]/', '', $body['id'] ?? ''),
        'name' => substr($body['name'] ?? 'Untitled', 0, 100),
        'tasks' => is_array($body['tasks'] ?? null) ? $body['tasks'] : [],
        'messages' => array_slice(is_array($body['messages'] ?? null) ? $body['messages'] : [], -80),
        'activity' => array_slice(is_array($body['activity'] ?? null) ? $body['activity'] : [], -50),
        'version' => intval($body['version'] ?? 0),
        'updatedAt' => time() * 1000,
        'updatedBy' => sanitize_role($body['updatedBy'] ?? 'dev1'),
    ];
}

$action = $_GET['action'] ?? 'team';
$body = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit();
    }
    $action = $body['action'] ?? $action;
}

switch ($action) {

    /* ── Team config (shared Groq key + project list) ── */
    case 'team':
        $team = load_team();
        echo json_encode([
            'groqKey' => $team['groqKey'] ?? '',
            'hasGroqKey' => !empty($team['groqKey']),
            'members' => is_array($team['members'] ?? null) ? $team['members'] : [],
            'projects' => $team['projects'] ?? [],
            'updatedAt' => $team['updatedAt'] ?? 0,
        ]);
        break;

    case 'save_team':
        $team = load_team();
        if (isset($body['groqKey'])) {
            $team['groqKey'] = substr($body['groqKey'], 0, 200);
        }
        if (isset($body['members']) && is_array($body['members'])) {
            $team['members'] = $body['members'];
        }
        save_team($team);
        echo json_encode(['ok' => true, 'hasGroqKey' => !empty($team['groqKey']), 'members' => $team['members']]);
        break;

    /* ── Projects ── */
    case 'list_projects':
        $team = load_team();
        echo json_encode(['projects' => $team['projects'] ?? []]);
        break;

    case 'get_project':
        $id = preg_replace('/[^a-z0-9\-_]/', '', $_GET['id'] ?? ($body['id'] ?? ''));
        $file = project_path($id);
        if (!file_exists($file)) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            break;
        }
        $data = read_json($file);
        echo json_encode($data);
        break;

    case 'create_project':
        $name = substr(trim($body['name'] ?? 'New Project'), 0, 100) ?: 'New Project';
        $id = slug_id($body['id'] ?? $name);
        $base = $id;
        $n = 1;
        while (file_exists(project_path($id))) {
            $id = $base . '-' . $n++;
        }
        $project = [
            'id' => $id,
            'name' => $name,
            'tasks' => $body['tasks'] ?? [],
            'messages' => $body['messages'] ?? [],
            'activity' => $body['activity'] ?? [],
            'version' => 1,
            'updatedAt' => time() * 1000,
            'updatedBy' => sanitize_role($body['updatedBy'] ?? 'dev1'),
            'commits' => [
                [
                    'id' => 'c' . (time() * 1000) . '-init',
                    'message' => 'Project created',
                    'author' => sanitize_role($body['updatedBy'] ?? 'dev1'),
                    'ts' => time() * 1000,
                    'version' => 1,
                    'snapshot' => [
                        'tasks' => $body['tasks'] ?? [],
                        'messages' => $body['messages'] ?? [],
                        'activity' => $body['activity'] ?? [],
                    ],
                ],
            ],
        ];
        write_json(project_path($id), $project);

        $team = load_team();
        $team['projects'][] = [
            'id' => $id,
            'name' => $name,
            'updatedAt' => $project['updatedAt'],
        ];
        save_team($team);
        echo json_encode(['ok' => true, 'project' => $project]);
        break;

    case 'save_project':
        $incoming = sanitize_project($body);
        if (!$incoming['id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing project id']);
            break;
        }
        $file = project_path($incoming['id']);
        $existing = read_json($file);

        if ($existing && isset($existing['version']) && isset($body['version'])) {
            $clientVersion = intval($body['version']);
            if ($clientVersion < $existing['version']) {
                echo json_encode([
                    'ok' => false,
                    'conflict' => true,
                    'project' => $existing,
                ]);
                break;
            }
        }

        $newVersion = ($existing['version'] ?? 0) + 1;
        $incoming['version'] = $newVersion;

        // ── Git-like single-branch commit history ──
        $existingCommits = is_array($existing['commits'] ?? null) ? $existing['commits'] : [];
        $commitMessage = isset($body['commitMessage']) && is_string($body['commitMessage'])
            ? substr(trim($body['commitMessage']), 0, 200)
            : ('Auto-save v' . $newVersion);
        $commit = [
            'id' => 'c' . (time() * 1000) . '-' . uniqid(),
            'message' => $commitMessage,
            'author' => sanitize_role($body['updatedBy'] ?? 'dev1'),
            'ts' => time() * 1000,
            'version' => $newVersion,
            'snapshot' => [
                'tasks' => $incoming['tasks'],
                'messages' => $incoming['messages'],
                'activity' => $incoming['activity'],
            ],
        ];
        $existingCommits[] = $commit;
        // Keep only the last 100 commits to prevent unbounded growth
        if (count($existingCommits) > 100) {
            $existingCommits = array_slice($existingCommits, -100);
        }
        $incoming['commits'] = $existingCommits;

        write_json($file, $incoming);

        $team = load_team();
        $found = false;
        foreach ($team['projects'] as &$p) {
            if ($p['id'] === $incoming['id']) {
                $p['name'] = $incoming['name'];
                $p['updatedAt'] = $incoming['updatedAt'];
                $found = true;
                break;
            }
        }
        unset($p);
        if (!$found) {
            $team['projects'][] = [
                'id' => $incoming['id'],
                'name' => $incoming['name'],
                'updatedAt' => $incoming['updatedAt'],
            ];
        }
        save_team($team);
        echo json_encode(['ok' => true, 'project' => $incoming]);
        break;

    case 'delete_project':
        $id = preg_replace('/[^a-z0-9\-_]/', '', $body['id'] ?? '');
        $file = project_path($id);
        if (file_exists($file)) {
            unlink($file);
        }
        $team = load_team();
        $team['projects'] = array_values(array_filter($team['projects'], function ($p) use ($id) {
            return $p['id'] !== $id;
        }));
        save_team($team);
        echo json_encode(['ok' => true]);
        break;

    /* ── Presence (pash / void online status) ── */
    case 'presence_push':
        $role = preg_replace('/[^a-zA-Z0-9\-_]/', '', $body['role'] ?? 'member');
        $name = substr($body['name'] ?? '', 0, 50);
        $projectId = preg_replace('/[^a-z0-9\-_]/', '', $body['projectId'] ?? '');
        $data = [
            'role' => $role,
            'name' => $name,
            'projectId' => $projectId,
            'tab' => substr($body['tab'] ?? 'board', 0, 20),
            'ts' => time() * 1000,
        ];
        write_json(DATA_DIR . 'presence_' . $role . '.json', $data);
        echo json_encode(['ok' => true, 'ts' => $data['ts']]);
        break;

    case 'presence_pull':
        $role = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['role'] ?? 'member');
        $file = DATA_DIR . 'presence_' . $role . '.json';
        if (file_exists($file)) {
            $data = read_json($file);
            $data['online'] = isset($data['ts']) && (time() * 1000 - $data['ts']) < 120000;
            echo json_encode($data);
        } else {
            echo json_encode(['online' => false, 'name' => $role]);
        }
        break;

    /* ── Legacy endpoints (backward compat) ── */
    case 'push':
        $role = sanitize_role($body['role'] ?? 'dev1');
        write_json(DATA_DIR . $role . '.json', [
            'role' => $role,
            'name' => substr($body['name'] ?? '', 0, 50),
            'project' => substr($body['project'] ?? '', 0, 100),
            'tasks' => $body['tasks'] ?? [],
            'messages' => array_slice($body['messages'] ?? [], -30),
            'activity' => array_slice($body['activity'] ?? [], -20),
            'ts' => time() * 1000,
        ]);
        echo json_encode(['ok' => true]);
        break;

    case 'pull':
        $role = sanitize_role($_GET['role'] ?? 'dev2');
        $file = DATA_DIR . $role . '.json';
        if (file_exists($file)) {
            $data = read_json($file);
            $data['online'] = isset($data['ts']) && (time() * 1000 - $data['ts']) < 120000;
            echo json_encode($data);
        } else {
            echo json_encode(['online' => false, 'tasks' => [], 'messages' => []]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}
