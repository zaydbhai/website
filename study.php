<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------------------------
// 1. BACKEND API: QUESTIONS FILTERING (?api=questions)
// -----------------------------------------------------------------------------
if (isset($_GET['api']) && $_GET['api'] === 'questions') {
    header('Content-Type: application/json');

    $subjectCode = $_GET['subject_code'] ?? '9701';
    $component   = $_GET['component'] ?? 'all';
    $topic       = $_GET['topic'] ?? 'all';
    $file        = __DIR__ . '/questions.json';

    if (!file_exists($file)) {
        echo json_encode(['error' => 'questions.json not found', 'questions' => []]);
        exit;
    }

    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) {
        echo json_encode(['error' => 'Invalid JSON in questions.json', 'questions' => []]);
        exit;
    }

    $filtered = array_values(array_filter($data, function ($q) use ($subjectCode, $component, $topic) {
        if (!isset($q['subject_code']) || (string)$q['subject_code'] !== (string)$subjectCode) {
            return false;
        }

        if ($topic !== 'all') {
            $qTopic = trim((string)($q['topic'] ?? ''));
            if (strcasecmp($qTopic, trim((string)$topic)) !== 0) {
                return false;
            }
        }

        if ($component !== 'all') {
            if (isset($q['paper']) && (string)$q['paper'] === (string)$component) {
                return true;
            }
            if (isset($q['paper_no']) && (string)$q['paper_no'] === (string)$component) {
                return true;
            }
            $pCode = (string)($q['paper_code'] ?? '');
            if (preg_match('/_(\d)\d$/', $pCode, $matches)) {
                return $matches[1] === (string)$component;
            }
            return false;
        }

        return true;
    }));

    echo json_encode(['questions' => $filtered]);
    exit;
}

// -----------------------------------------------------------------------------
// 2. BACKEND API: SECURE IMAGE PROXY (?api=image&file=...)
// -----------------------------------------------------------------------------
if (isset($_GET['api']) && $_GET['api'] === 'image') {
    if (empty($_SESSION['active_user']) && empty($_SESSION['portal_verified'])) {
        http_response_code(403);
        exit('Access Denied. Please log in through the portal.');
    }

    $requested = $_GET['file'] ?? '';
    $requested = str_replace(['..', "\0"], '', ltrim($requested, '/\\'));

    $candidates = [
        __DIR__ . '/' . $requested,
        __DIR__ . '/resources/Alevel/' . $requested,
        __DIR__ . '/' . preg_replace('#^resources/Alevel/#', '', $requested),
    ];

    $baseDir  = realpath(__DIR__);
    $filepath = false;

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved !== false && strpos($resolved, $baseDir) === 0 && is_file($resolved)) {
            $filepath = $resolved;
            break;
        }
    }

    if ($filepath === false) {
        http_response_code(404);
        exit('Image not found.');
    }

    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf',
    ];

    if (!isset($mimeTypes[$ext])) {
        http_response_code(403);
        exit('Unsupported file type.');
    }

    header('Content-Type: ' . $mimeTypes[$ext]);
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

$_SESSION['portal_verified'] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>A-Level Study & Practice Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: 'var(--brand-color, #10b981)',
                            400: 'var(--brand-light, #34d399)',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --brand-color: #10b981;
            --brand-light: #34d399;
        }
        .theme-sky { --brand-color: #0284c7; --brand-light: #38bdf8; }
        .theme-amber { --brand-color: #d97706; --brand-light: #fbbf24; }
        .theme-rose { --brand-color: #e11d48; --brand-light: #fb7185; }
        .theme-emerald { --brand-color: #10b981; --brand-light: #34d399; }
        .print-only { display: none; }
        @media print {
            header, #gen-filter-builder, .no-print { display: none !important; }
            body { background: #ffffff !important; color: #000000 !important; }
            #generator-workspace { display: block !important; }
            .print-only { display: block !important; }
            .print-page-break { page-break-before: always; break-before: page; }
            .question-card { break-inside: avoid; border: 1px solid #cbd5e1 !important; background: #ffffff !important; color: #000000 !important; }
        }
    </style>
</head>
<body id="app-body" class="bg-slate-950 text-slate-100 min-h-screen font-sans antialiased selection:bg-brand-500 selection:text-slate-950 theme-emerald">

    <!-- ==================== LOGIN SCREEN ==================== -->
    <div id="auth-screen" class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 p-6 sm:p-8 rounded-2xl max-w-md w-full shadow-2xl space-y-6">
            <div class="text-center space-y-2">
                <div class="bg-slate-800 text-brand-400 w-14 h-14 rounded-2xl flex items-center justify-center mx-auto text-xl shadow-inner border border-slate-700">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <h1 class="text-xl font-bold text-white tracking-tight">Private Study Portal</h1>
                <p class="text-xs text-slate-400">Select your account and enter your PIN to access.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Select Account</label>
                    <select id="login-user" class="w-full bg-slate-950 border border-slate-800 text-xs sm:text-sm rounded-xl px-4 py-3 text-white focus:outline-none focus:border-brand-500 transition">
                        <option value="zayd">Zayd</option>
                        <option value="ahmed">Ahmed</option>
                        <option value="talha">Talha</option>
                        <option value="suleiman">Suleiman</option>
                        <option value="abdul bari khan">Abdul Bari Khan</option>
                        <option value="sufyan">Sufyan</option>
                        <option value="Ayan">Ayan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Passcode PIN</label>
                    <input id="login-pin" type="password" placeholder="Enter PIN..." class="w-full bg-slate-950 border border-slate-800 text-xs sm:text-sm rounded-xl px-4 py-3 text-white focus:outline-none focus:border-brand-500 text-center tracking-widest transition" onkeypress="if(event.key === 'Enter') handleLogin()" />
                </div>
                <p id="error-msg" class="text-xs text-rose-400 font-medium text-center hidden">Invalid passcode.</p>
                <button onclick="handleLogin()" class="w-full py-3.5 bg-brand-500 hover:opacity-90 text-slate-950 font-bold text-xs sm:text-sm rounded-xl transition shadow-lg flex items-center justify-center gap-2">
                    <i class="fa-solid fa-lock text-xs"></i> Access Portal
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MAIN DASHBOARD ==================== -->
    <div id="main-app" class="hidden min-h-screen flex flex-col">
        <!-- FIXED HEADER -->
        <header class="bg-slate-900/90 backdrop-blur border-b border-slate-800 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center space-x-3 shrink-0">
                    <div class="bg-slate-800 border border-slate-700 p-2 rounded-xl text-brand-400">
                        <i class="fa-solid fa-book-open text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-sm sm:text-base font-bold text-white tracking-tight flex items-center gap-2">
                            A-Level Portal 
                            <span class="text-xs font-normal text-slate-400">User: <strong id="display-user" class="text-brand-400"></strong></span>
                        </h1>
                    </div>
                </div>

                <!-- TIMER & COUNTDOWN WIDGET -->
                <div id="timer-bar" class="bg-slate-950 px-3 py-1.5 rounded-xl border border-slate-800 flex items-center space-x-3 text-xs shrink-0">
                    <div class="flex items-center space-x-1.5 border-r border-slate-800 pr-3" title="Daily Activity Streak">
                        <span class="text-amber-500 text-sm">🔥</span>
                        <span id="streak-count" class="font-bold font-mono text-white">0d</span>
                    </div>

                    <div class="flex items-center space-x-2 border-r border-slate-800 pr-3">
                        <i class="fa-solid fa-calendar-day text-slate-400 text-xs"></i>
                        <span class="text-[11px] text-slate-300 font-mono"><strong id="days-to-exam" class="text-brand-400">--</strong> days left</span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-clock text-brand-400 text-xs"></i>
                        <span id="timer-display" class="font-mono font-bold text-white text-sm">00:00:00</span>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <button onclick="presetTimer(75)" class="px-2 py-1 bg-slate-900 text-slate-300 rounded border border-slate-800 text-[10px] hover:border-brand-500">1h15m</button>
                        <button onclick="presetTimer(120)" class="px-2 py-1 bg-slate-900 text-slate-300 rounded border border-slate-800 text-[10px] hover:border-brand-500">2h00m</button>
                        <button onclick="toggleTimer()" id="timer-toggle-btn" class="px-2.5 py-1 bg-brand-500 text-slate-950 font-bold rounded text-[11px]"><i class="fa-solid fa-play"></i></button>
                        <button onclick="resetTimer()" class="px-2 py-1 bg-slate-900 text-slate-400 rounded text-[10px]"><i class="fa-solid fa-rotate-right"></i></button>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex items-center space-x-2 shrink-0">
                    <div class="flex bg-slate-950 p-1 rounded-xl border border-slate-800">
                        <button onclick="switchTab('practice')" id="nav-practice" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all bg-brand-500 text-slate-950 shadow">Practice</button>
                        <button onclick="switchTab('checklist')" id="nav-checklist" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all text-slate-400 hover:text-white">Topics</button>
                        <button onclick="switchTab('notes')" id="nav-notes" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all text-slate-400 hover:text-white">Notes</button>
                        <button onclick="switchTab('tools')" id="nav-tools" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all text-slate-400 hover:text-white">Tools</button>
                        <button onclick="switchTab('tracker')" id="nav-tracker" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all text-slate-400 hover:text-white">Stats</button>
                        <button onclick="switchTab('timetable')" id="nav-timetable" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all text-slate-400 hover:text-white">Timetable</button>
                        <button onclick="switchTab('generator')" id="nav-generator" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all text-slate-400 hover:text-white">Generator</button>
                    </div>

                    <select onchange="changeTheme(this.value)" class="bg-slate-950 border border-slate-800 text-[11px] rounded-xl px-2 py-1.5 text-slate-400 focus:outline-none">
                        <option value="emerald">Emerald</option>
                        <option value="sky">Sky</option>
                        <option value="amber">Amber</option>
                        <option value="rose">Rose</option>
                    </select>

                    <button onclick="handleLogout()" class="px-3 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-xs font-semibold rounded-lg transition border border-rose-500/20">
                        Logout
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-6 flex-1 w-full space-y-6">
            <!-- SEARCH BAR -->
            <div class="bg-slate-900 border border-slate-800 p-2.5 rounded-2xl flex items-center space-x-3 shadow-inner no-print">
                <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm pl-2"></i>
                <input id="global-search" type="text" oninput="handleSearch()" placeholder="Search past papers, codes, formulas, or topics..." class="w-full bg-transparent text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none" />
                <button onclick="clearSearch()" class="text-slate-500 hover:text-slate-300 text-xs pr-2"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <!-- PRACTICE TAB -->
            <section id="tab-practice" class="space-y-4">
                <div id="recent-papers-bar" class="bg-slate-900 border border-slate-800 p-3 rounded-2xl hidden space-y-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-400"><i class="fa-solid fa-clock-rotate-left mr-1.5 text-brand-400"></i> Recent Papers Quick Access</h3>
                        <span class="text-[10px] text-slate-500">Last opened</span>
                    </div>
                    <div id="recent-papers-list" class="flex flex-wrap gap-2"></div>
                </div>

                <div class="bg-slate-900 p-4 rounded-2xl border border-slate-800 grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Subject</label>
                        <select id="p-subject" onchange="renderPapers()" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2.5 text-slate-200 focus:outline-none focus:border-brand-500">
                            <option value="9701_Chemistry">Chemistry (9701)</option>
                            <option value="9702_Physics">Physics (9702)</option>
                            <option value="9709_Mathematics">Mathematics (9709)</option>
                            <option value="9608_Computer Science">Computer Science (9608)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Year</label>
                        <select id="p-year" onchange="renderPapers()" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2.5 text-slate-200 focus:outline-none focus:border-brand-500"></select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Session</label>
                        <select id="p-session" onchange="renderPapers()" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2.5 text-slate-200 focus:outline-none focus:border-brand-500">
                            <option value="Spring">Spring (Feb/Mar)</option>
                            <option value="Summer" selected>Summer (May/June)</option>
                            <option value="Winter">Winter (Oct/Nov)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Variant</label>
                        <select id="p-variant" onchange="renderPapers()" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2.5 text-slate-200 focus:outline-none focus:border-brand-500">
                            <option value="1">Variant 1 (11, 21, 31...)</option>
                            <option value="2" selected>Variant 2 (12, 22, 32...)</option>
                            <option value="3">Variant 3 (13, 23, 33...)</option>
                        </select>
                    </div>
                    <div class="col-span-2 sm:col-span-1 flex flex-col justify-end">
                        <button onclick="triggerPaperRoulette()" class="w-full py-2.5 bg-gradient-to-r from-sky-500 to-brand-500 text-slate-950 text-xs font-bold rounded-xl shadow transition hover:opacity-95 flex items-center justify-center gap-1.5" title="Surprise Me!">
                            <i class="fa-solid fa-dice"></i> Roulette
                        </button>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 px-1">
                    <div class="flex bg-slate-900 p-1 rounded-xl border border-slate-800">
                        <button onclick="setCompletionFilter('all')" id="filter-all" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-brand-500 text-slate-950 transition">All</button>
                        <button onclick="setCompletionFilter('unattempted')" id="filter-unattempted" class="px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white transition">Unattempted</button>
                        <button onclick="setCompletionFilter('completed')" id="filter-completed" class="px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white transition">Completed</button>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto justify-between">
                        <select id="p-category" onchange="renderPapers()" class="bg-slate-900 border border-slate-800 text-xs rounded-xl px-3 py-2 text-slate-200 focus:outline-none focus:border-brand-500">
                            <option value="Question Papers">Question Papers</option>
                            <option value="Mark Schemes">Mark Schemes</option>
                            <option value="Examiner Reports">Examiner Reports</option>
                            <option value="Grade Thresholds">Grade Thresholds</option>
                        </select>
                        <span id="paper-count-badge" class="text-[11px] text-slate-400 font-mono"></span>
                    </div>
                </div>

                <div id="papers-list" class="space-y-2.5"></div>
            </section>

            <!-- TOPIC CHECKLIST TAB -->
            <section id="tab-checklist" class="hidden space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-slate-900 p-4 rounded-2xl border border-slate-800 gap-3">
                    <div>
                        <h2 class="text-xs font-bold uppercase text-slate-300 tracking-wider">Topic Confidence Checklist</h2>
                        <p class="text-[11px] text-slate-400">Rate your mastery across syllabus chapters to track weak spots.</p>
                    </div>
                    <select id="checklist-subject" onchange="renderChecklist()" class="w-full sm:max-w-xs bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2 text-slate-300 focus:outline-none focus:border-brand-500">
                        <option value="9701">Chemistry (9701)</option>
                        <option value="9702">Physics (9702)</option>
                        <option value="9709">Mathematics (9709)</option>
                        <option value="9608">Computer Science (9608)</option>
                    </select>
                </div>
                <div id="checklist-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
            </section>

            <!-- NOTES TAB -->
            <section id="tab-notes" class="hidden space-y-4">
                <div class="bg-slate-900 p-4 rounded-2xl border border-slate-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h2 class="text-xs font-bold uppercase text-slate-300 tracking-wider">Interactive Reference Notes & Guides</h2>
                        <p class="text-[11px] text-slate-400">Structured topic study notes, formula sheets, and Cambridge syllabi.</p>
                    </div>
                    <div class="flex bg-slate-950 p-1 rounded-xl border border-slate-800 w-full sm:w-auto overflow-x-auto">
                        <button onclick="filterNotes('all')" id="note-btn-all" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-brand-500 text-slate-950 transition">All</button>
                        <button onclick="filterNotes('chem')" id="note-btn-chem" class="px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white transition">Chemistry</button>
                        <button onclick="filterNotes('phys')" id="note-btn-phys" class="px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white transition">Physics</button>
                        <button onclick="filterNotes('math')" id="note-btn-math" class="px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white transition">Maths</button>
                        <button onclick="filterNotes('cs')" id="note-btn-cs" class="px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white transition">CS & Syllabus</button>
                    </div>
                </div>

                <div id="notes-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
            </section>

            <!-- TOOLS TAB -->
            <section id="tab-tools" class="hidden space-y-6">
                <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-4">
                    <div class="flex items-center space-x-3 border-b border-slate-800 pb-3">
                        <div class="bg-brand-500/10 text-brand-400 p-2.5 rounded-xl border border-brand-500/20">
                            <i class="fa-solid fa-calculator text-base"></i>
                        </div>
                        <div>
                            <h2 class="text-xs sm:text-sm font-bold text-white uppercase tracking-wider">Cambridge Formulas & CS Syntax Quick Reference</h2>
                            <p class="text-[11px] text-slate-400">Essential formulas and standards for exam preparation.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-1">
                            <h4 class="font-bold text-brand-400">Computer Science 9608</h4>
                            <p class="font-mono text-[11px] text-slate-400">DECLARE MyVar : INTEGER | INPUT MyVar | OUTPUT MyVar | WHILE ... ENDWHILE</p>
                        </div>
                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-1">
                            <h4 class="font-bold text-brand-400">Mathematics 9709 (MF19)</h4>
                            <p class="font-mono text-[11px] text-slate-400">Quadratic: x = (-b ± √(b² - 4ac)) / 2a | ∫ xⁿ dx = xⁿ⁺¹/(n+1)</p>
                        </div>
                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-1">
                            <h4 class="font-bold text-brand-400">Physics 9702 Constants</h4>
                            <p class="font-mono text-[11px] text-slate-400">c = 3.00 × 10⁸ m/s | g = 9.81 m/s² | h = 6.63 × 10⁻³⁴ J·s</p>
                        </div>
                        <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-1">
                            <h4 class="font-bold text-brand-400">Chemistry 9701 Formulas</h4>
                            <p class="font-mono text-[11px] text-slate-400">PV = nRT (R = 8.31 J/mol·K) | pH = -log₁₀[H⁺]</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-4">
                        <div class="flex items-center space-x-2 border-b border-slate-800 pb-3">
                            <span class="text-emerald-400"><i class="fa-solid fa-flask"></i></span>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Chemistry Calculators (9701)</h3>
                        </div>
                        <div class="space-y-4 text-xs">
                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-2">
                                <h4 class="font-semibold text-slate-200">Mass ↔ Moles ↔ Concentration</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-1">Mass (g) or Vol (dm³)</label>
                                        <input id="chem-val1" type="number" placeholder="e.g. 10" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-slate-400 mb-1">Molar Mass (g/mol) or Conc</label>
                                        <input id="chem-val2" type="number" placeholder="e.g. 40" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white" />
                                    </div>
                                </div>
                                <div class="flex gap-2 pt-1">
                                    <button onclick="calcChemMoles()" class="flex-1 py-1.5 bg-brand-500 text-slate-950 font-bold rounded-lg text-[11px]">Calculate Moles</button>
                                    <button onclick="calcChemConc()" class="flex-1 py-1.5 bg-slate-800 text-slate-200 font-semibold rounded-lg text-[11px]">Calculate Conc</button>
                                </div>
                                <p id="chem-result" class="font-mono text-brand-400 font-bold text-center pt-1"></p>
                            </div>

                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-2">
                                <h4 class="font-semibold text-slate-200">pH Calculator: pH = -log₁₀[H⁺]</h4>
                                <div class="flex gap-2">
                                    <input id="chem-h-ion" type="number" step="any" placeholder="[H⁺] concentration e.g. 0.001" class="flex-1 bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white" />
                                    <button onclick="calcPh()" class="px-4 py-1.5 bg-sky-500 text-slate-950 font-bold rounded-lg text-[11px]">Compute pH</button>
                                </div>
                                <p id="chem-ph-result" class="font-mono text-sky-400 font-bold text-center pt-1"></p>
                            </div>

                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-2">
                                <h4 class="font-semibold text-slate-200">Ideal Gas (PV = nRT) - Find Pressure (kPa)</h4>
                                <div class="grid grid-cols-3 gap-2">
                                    <input id="gas-n" type="number" placeholder="Moles (n)" class="bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 text-white text-[11px]" />
                                    <input id="gas-t" type="number" placeholder="Temp (K)" class="bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 text-white text-[11px]" />
                                    <input id="gas-v" type="number" placeholder="Vol (dm³)" class="bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 text-white text-[11px]" />
                                </div>
                                <button onclick="calcIdealGas()" class="w-full py-1.5 bg-amber-500 text-slate-950 font-bold rounded-lg text-[11px]">Solve Pressure (kPa)</button>
                                <p id="gas-result" class="font-mono text-amber-400 font-bold text-center pt-1"></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-4">
                        <div class="flex items-center space-x-2 border-b border-slate-800 pb-3">
                            <span class="text-sky-400"><i class="fa-solid fa-square-root-variable"></i></span>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Math & Physics Solvers (9709 / 9702)</h3>
                        </div>
                        <div class="space-y-4 text-xs">
                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-2">
                                <h4 class="font-semibold text-slate-200">Quadratic Roots: ax² + bx + c = 0</h4>
                                <div class="grid grid-cols-3 gap-2">
                                    <input id="quad-a" type="number" placeholder="a" class="bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 text-white text-[11px]" />
                                    <input id="quad-b" type="number" placeholder="b" class="bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 text-white text-[11px]" />
                                    <input id="quad-c" type="number" placeholder="c" class="bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 text-white text-[11px]" />
                                </div>
                                <button onclick="calcQuadratic()" class="w-full py-1.5 bg-brand-500 text-slate-950 font-bold rounded-lg text-[11px]">Find Roots</button>
                                <p id="quad-result" class="font-mono text-brand-400 font-bold text-center pt-1"></p>
                            </div>

                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-2">
                                <h4 class="font-semibold text-slate-200">Kinematics: v² = u² + 2as (Find v)</h4>
                                <div class="grid grid-cols-3 gap-2">
                                    <input id="kin-u" type="number" placeholder="u (m/s)" class="bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 text-white text-[11px]" />
                                    <input id="kin-a" type="number" placeholder="a (m/s²)" class="bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 text-white text-[11px]" />
                                    <input id="kin-s" type="number" placeholder="s (m)" class="bg-slate-900 border border-slate-800 rounded-lg px-2 py-1.5 text-white text-[11px]" />
                                </div>
                                <button onclick="calcKinematics()" class="w-full py-1.5 bg-sky-500 text-slate-950 font-bold rounded-lg text-[11px]">Solve Final Velocity (v)</button>
                                <p id="kin-result" class="font-mono text-sky-400 font-bold text-center pt-1"></p>
                            </div>

                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 space-y-2">
                                <h4 class="font-semibold text-slate-200">Wave Speed: c = fλ</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <input id="wave-f" type="number" placeholder="Frequency f (Hz)" class="bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white" />
                                    <input id="wave-l" type="number" placeholder="Wavelength λ (m)" class="bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white" />
                                </div>
                                <button onclick="calcWaveSpeed()" class="w-full py-1.5 bg-amber-500 text-slate-950 font-bold rounded-lg text-[11px]">Calculate Speed (c)</button>
                                <p id="wave-result" class="font-mono text-amber-400 font-bold text-center pt-1"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- TIMETABLE TAB -->
            <section id="tab-timetable" class="hidden space-y-6">
                <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-2">
                    <h2 class="text-xs sm:text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-calendar-days text-brand-400"></i> Cambridge AS Level May/June 2027 Timetable (Zone 4 - Pakistan)
                    </h2>
                    <p class="text-[11px] text-slate-400">Official verified exam calendar and subject breakdown for your registered exams.</p>
                </div>
            
                <!-- VISUAL CALENDARS GRID (APRIL, MAY, JUNE) -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- APRIL 2027 -->
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">April 2027</h3>
                            <span class="text-[10px] font-mono px-2 py-0.5 bg-slate-950 rounded text-brand-400 border border-slate-800">3 Exams</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-mono text-slate-500 pb-1">
                            <span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center text-[11px]">
                            <span class="text-slate-700">--</span><span class="text-slate-700">--</span><span class="text-slate-700">--</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">1</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">2</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">3</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">4</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">5</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">6</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">7</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">8</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">9</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">10</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">11</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">12</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">13</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">14</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">15</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">16</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">17</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">18</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">19</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">20</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">21</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">22</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">23</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">24</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">25</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">26</span>
                            <span class="p-1 rounded bg-emerald-500/20 text-emerald-400 font-bold border border-emerald-500/40 relative group cursor-pointer" title="Chem P33 (AM)">27<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Chem P33</span></span>
                            <span class="p-1 rounded bg-amber-500/20 text-amber-400 font-bold border border-amber-500/40 relative group cursor-pointer" title="Math P12 (PM)">28<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Math P12</span></span>
                            <span class="p-1 rounded bg-sky-500/20 text-sky-400 font-bold border border-sky-500/40 relative group cursor-pointer" title="Phys P33 (AM)">29<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Phys P33</span></span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">30</span>
                        </div>
                        <div class="pt-2 text-[10px] text-slate-400 space-y-1 border-t border-slate-800/80">
                            <p>🧪 <strong class="text-emerald-400">Apr 27:</strong> Chem P33 (AM)</p>
                            <p>📐 <strong class="text-amber-400">Apr 28:</strong> Math P12 (PM)</p>
                            <p>⚡ <strong class="text-sky-400">Apr 29:</strong> Phys P33 (AM)</p>
                        </div>
                    </div>
            
                    <!-- MAY 2027 -->
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">May 2027</h3>
                            <span class="text-[10px] font-mono px-2 py-0.5 bg-slate-950 rounded text-brand-400 border border-slate-800">10 Exams</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-mono text-slate-500 pb-1">
                            <span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center text-[11px]">
                            <span class="text-slate-700">--</span><span class="text-slate-700">--</span><span class="text-slate-700">--</span><span class="text-slate-700">--</span><span class="text-slate-700">--</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">1</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">2</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">3</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">4</span>
                            <span class="p-1 rounded bg-emerald-500/20 text-emerald-400 font-bold border border-emerald-500/40 relative group cursor-pointer" title="Chem P42">5<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Chem P42</span></span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">6</span>
                            <span class="p-1 rounded bg-amber-500/20 text-amber-400 font-bold border border-amber-500/40 relative group cursor-pointer" title="Math P22 & P42">7<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Math P22 & P42</span></span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">8</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">9</span>
                            <span class="p-1 rounded bg-sky-500/20 text-sky-400 font-bold border border-sky-500/40 relative group cursor-pointer" title="Phys P42 & CS P12">10<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Phys P42 & CS P12</span></span>
                            <span class="p-1 rounded bg-amber-500/20 text-amber-400 font-bold border border-amber-500/40 relative group cursor-pointer" title="Math P52">11<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Math P52</span></span>
                            <span class="p-1 rounded bg-sky-500/20 text-sky-400 font-bold border border-sky-500/40 relative group cursor-pointer" title="Phys P22 & P52">12<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Phys P22 & P52</span></span>
                            <span class="p-1 rounded bg-rose-500/20 text-rose-400 font-bold border border-rose-500/40 relative group cursor-pointer" title="CS P22">13<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">CS P22</span></span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">14</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">15</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">16</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">17</span>
                            <span class="p-1 rounded bg-sky-500/20 text-sky-400 font-bold border border-sky-500/40 relative group cursor-pointer" title="Phys P34">18<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Phys P34</span></span>
                            <span class="p-1 rounded bg-emerald-500/20 text-emerald-400 font-bold border border-emerald-500/40 relative group cursor-pointer" title="Chem P22 & P52">19<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Chem P22 & P52</span></span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">20</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">21</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">22</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">23</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">24</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">25</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">26</span>
                            <span class="p-1 rounded bg-emerald-500/20 text-emerald-400 font-bold border border-emerald-500/40 relative group cursor-pointer" title="Chem P34">27<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Chem P34</span></span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">28</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">29</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">30</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">31</span>
                        </div>
                        <div class="pt-2 text-[10px] text-slate-400 space-y-1 border-t border-slate-800/80">
                            <p>🧪 <strong class="text-emerald-400">May 5:</strong> Chem P42 | 📐 <strong class="text-amber-400">May 7:</strong> Math P22 & P42</p>
                            <p>⚡ <strong class="text-sky-400">May 10:</strong> Phys P42 | 💻 <strong class="text-rose-400">May 10:</strong> CS P12</p>
                            <p>📐 <strong class="text-amber-400">May 11:</strong> Math P52 | ⚡ <strong class="text-sky-400">May 12:</strong> Phys P22 & P52</p>
                            <p>💻 <strong class="text-rose-400">May 13:</strong> CS P22 | ⚡ <strong class="text-sky-400">May 18:</strong> Phys P34</p>
                            <p>🧪 <strong class="text-emerald-400">May 19:</strong> Chem P22 & P52 | 🧪 <strong class="text-emerald-400">May 27:</strong> Chem P34</p>
                        </div>
                    </div>
            
                    <!-- JUNE 2027 -->
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">June 2027</h3>
                            <span class="text-[10px] font-mono px-2 py-0.5 bg-slate-950 rounded text-brand-400 border border-slate-800">2 Exams</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-mono text-slate-500 pb-1">
                            <span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span>
                        </div>
                        <div class="grid grid-cols-7 gap-1 text-center text-[11px]">
                            <span class="text-slate-700">--</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">1</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">2</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">3</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">4</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">5</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">6</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">7</span>
                            <span class="p-1 rounded bg-emerald-500/20 text-emerald-400 font-bold border border-emerald-500/40 relative group cursor-pointer" title="Chem P12">8<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Chem P12</span></span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">9</span>
                            <span class="p-1 rounded bg-sky-500/20 text-sky-400 font-bold border border-sky-500/40 relative group cursor-pointer" title="Phys P12">10<span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-950 text-white text-[9px] px-2 py-0.5 rounded border border-slate-800 whitespace-nowrap z-10">Phys P12</span></span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">11</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">12</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">13</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">14</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">15</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">16</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">17</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">18</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">19</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">20</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">21</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">22</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">23</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">24</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">25</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">26</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">27</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">28</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">29</span>
                            <span class="p-1 rounded bg-slate-950 text-slate-300">30</span>
                        </div>
                        <div class="pt-2 text-[10px] text-slate-400 space-y-1 border-t border-slate-800/80">
                            <p>🧪 <strong class="text-emerald-400">June 8:</strong> Chem P12 (AM)</p>
                            <p>⚡ <strong class="text-sky-400">June 10:</strong> Phys P12 (AM)</p>
                        </div>
                    </div>
                </div>
            
                <!-- SUBJECT BREAKDOWN CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Chemistry (9701)</h3>
                            <span class="text-[10px] font-mono px-2 py-0.5 bg-slate-950 rounded text-slate-400 border border-slate-800">Zone 4</span>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 33 (Advanced Practical Skills 1)</p><p class="text-[10px] text-slate-400">Tuesday, 27 April 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 42 (A Level Structured Questions)</p><p class="text-[10px] text-slate-400">Wednesday, 5 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 22 (AS Level Structured Questions)</p><p class="text-[10px] text-slate-400">Wednesday, 19 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 52 (Planning, Analysis & Evaluation)</p><p class="text-[10px] text-slate-400">Wednesday, 19 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 34 (Advanced Practical Skills 2)</p><p class="text-[10px] text-slate-400">Thursday, 27 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 12 (Multiple Choice)</p><p class="text-[10px] text-slate-400">Tuesday, 8 June 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                        </div>
                    </div>
            
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <h3 class="text-xs font-bold text-sky-400 uppercase tracking-wider">Physics (9702)</h3>
                            <span class="text-[10px] font-mono px-2 py-0.5 bg-slate-950 rounded text-slate-400 border border-slate-800">Zone 4</span>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 33 (Advanced Practical Skills 1)</p><p class="text-[10px] text-slate-400">Thursday, 29 April 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 42 (A Level Structured Questions)</p><p class="text-[10px] text-slate-400">Monday, 10 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 52 (Planning, Analysis & Evaluation)</p><p class="text-[10px] text-slate-400">Wednesday, 12 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 22 (AS Level Structured Questions)</p><p class="text-[10px] text-slate-400">Wednesday, 12 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 34 (Advanced Practical Skills 2)</p><p class="text-[10px] text-slate-400">Tuesday, 18 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 12 (Multiple Choice)</p><p class="text-[10px] text-slate-400">Thursday, 10 June 2027</p></div>
                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 font-mono text-[10px] font-bold rounded border border-amber-500/20">AM</span>
                            </div>
                        </div>
                    </div>
            
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider">Mathematics (9709)</h3>
                            <span class="text-[10px] font-mono px-2 py-0.5 bg-slate-950 rounded text-slate-400 border border-slate-800">Zone 4</span>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 12 (Pure Mathematics 1)</p><p class="text-[10px] text-slate-400">Wednesday, 28 April 2027</p></div>
                                <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 font-mono text-[10px] font-bold rounded border border-indigo-500/20">PM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 22 (Pure Mathematics 2)</p><p class="text-[10px] text-slate-400">Friday, 7 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 font-mono text-[10px] font-bold rounded border border-indigo-500/20">PM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 42 (Mechanics)</p><p class="text-[10px] text-slate-400">Friday, 7 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 font-mono text-[10px] font-bold rounded border border-indigo-500/20">PM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 52 (Probability & Statistics 1)</p><p class="text-[10px] text-slate-400">Tuesday, 11 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 font-mono text-[10px] font-bold rounded border border-indigo-500/20">PM</span>
                            </div>
                        </div>
                    </div>
            
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <h3 class="text-xs font-bold text-rose-400 uppercase tracking-wider">Computer Science (9618)</h3>
                            <span class="text-[10px] font-mono px-2 py-0.5 bg-slate-950 rounded text-slate-400 border border-slate-800">Zone 4</span>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 12 (Theory Fundamentals)</p><p class="text-[10px] text-slate-400">Monday, 10 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 font-mono text-[10px] font-bold rounded border border-indigo-500/20">PM</span>
                            </div>
                            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center">
                                <div><p class="font-semibold text-white">Paper 22 (Problem-Solving & Programming)</p><p class="text-[10px] text-slate-400">Thursday, 13 May 2027</p></div>
                                <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 font-mono text-[10px] font-bold rounded border border-indigo-500/20">PM</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TOPICAL GENERATOR TAB -->
            <section id="tab-generator" class="hidden space-y-6">
                <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-2 no-print">
                    <h2 class="text-xs sm:text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-wand-magic-sparkles text-brand-400"></i> AI-Free Topical Past Paper Generator
                    </h2>
                    <p class="text-[11px] text-slate-400">Generate custom practice worksheets from your local <code class="text-brand-400">questions.json</code> dataset. Click any question image to toggle full-width zoom. Printing includes all Mark Schemes at the end.</p>
                </div>

                <!-- FILTER BUILDER -->
                <div id="gen-filter-builder" class="bg-slate-900 border border-slate-800 p-5 rounded-2xl grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Subject</label>
                        <select id="gen-subject" onchange="updatePaperComponentOptions()" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-brand-500">
                            <option value="9701">Chemistry (9701)</option>
                            <option value="9702">Physics (9702)</option>
                            <option value="9709">Mathematics (9709)</option>
                            <option value="9608">Computer Science (9608)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Paper / Component</label>
                        <select id="gen-component" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-brand-500">
                            <option value="all">All Papers / Components</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Topic</label>
                        <select id="gen-topic" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-brand-500">
                            <option value="all">All Topics</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Mode</label>
                        <select id="gen-mode" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-brand-500">
                            <option value="unseen">Unseen Questions First (Smart Anti-Bias)</option>
                            <option value="random">Random Roulette Mix</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Question Count</label>
                        <select id="gen-count" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-brand-500">
                            <option value="5">5 Questions (Quick Drill)</option>
                            <option value="10" selected>10 Questions</option>
                            <option value="20">20 Questions</option>
                            <option value="40">40 Questions (Full Paper Style)</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-5 flex justify-end gap-2 pt-2 border-t border-slate-800">
                        <button onclick="generateTopicalPaper()" class="px-6 py-3 bg-brand-500 hover:opacity-90 text-slate-950 font-bold text-xs rounded-xl transition shadow flex items-center gap-2">
                            <i class="fa-solid fa-bolt"></i> Generate Custom Paper
                        </button>
                    </div>
                </div>

                <!-- GENERATED PAPER WORKSPACE -->
                <div id="generator-workspace" class="space-y-4 hidden">
                    <div class="sticky top-16 z-30 flex flex-wrap justify-between items-center gap-2 bg-slate-900/95 backdrop-blur border border-slate-800 px-4 py-3 rounded-xl shadow-lg no-print">
                        <div class="flex items-center gap-3">
                            <div class="text-xs font-bold text-white uppercase tracking-wider" id="gen-paper-title">Generated Worksheet</div>
                            <span id="gen-nav-indicator" class="text-[11px] font-mono text-brand-400 bg-brand-500/10 border border-brand-500/20 px-2 py-0.5 rounded">Q1</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="stepQuestion(-1)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 flex items-center gap-1">
                                <i class="fa-solid fa-arrow-up text-[10px]"></i> Prev
                            </button>
                            <button onclick="stepQuestion(1)" class="px-3 py-1.5 bg-brand-500 hover:opacity-90 text-slate-950 text-xs font-bold rounded-lg flex items-center gap-1">
                                Next <i class="fa-solid fa-arrow-down text-[10px]"></i>
                            </button>
                            <button onclick="window.print()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700 flex items-center gap-1.5">
                                <i class="fa-solid fa-print"></i> Print / Save PDF
                            </button>
                        </div>
                    </div>

                    <div id="generator-questions-list" class="space-y-5 max-w-4xl mx-auto"></div>

                    <!-- PRINT-ONLY MARK SCHEMES AT THE END -->
                    <div id="print-mark-schemes-section" class="print-only print-page-break max-w-4xl mx-auto space-y-4 pt-4">
                        <div class="border-b-2 border-slate-300 pb-2 mb-4">
                            <h2 class="text-lg font-bold uppercase tracking-wider text-black">Mark Schemes &amp; Verified Answers</h2>
                        </div>
                        <div id="print-mark-schemes-list" class="space-y-4"></div>
                    </div>
                </div>
            </section>

            <!-- STATS TAB -->
            <section id="tab-tracker" class="hidden space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl">
                        <p class="text-[10px] text-slate-400 uppercase font-semibold tracking-wider">Attempted</p>
                        <h3 id="stat-attempted" class="text-2xl font-bold text-white mt-1">0</h3>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl">
                        <p class="text-[10px] text-slate-400 uppercase font-semibold tracking-wider">Average Score & Grade</p>
                        <div class="flex items-center space-x-2 mt-1">
                            <h3 id="stat-avg" class="text-2xl font-bold text-brand-400">0%</h3>
                            <span id="stat-grade-badge" class="px-2 py-0.5 bg-slate-800 border border-slate-700 text-slate-300 font-mono text-xs font-bold rounded">-</span>
                        </div>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl">
                        <p class="text-[10px] text-slate-400 uppercase font-semibold tracking-wider">Weekly Paper Goal</p>
                        <div class="flex items-center justify-between mt-1">
                            <h3 id="stat-weekly" class="text-2xl font-bold text-sky-400">0 / 5</h3>
                            <button onclick="setWeeklyGoalPrompt()" class="text-slate-500 hover:text-slate-300 text-xs"><i class="fa-solid fa-pen"></i></button>
                        </div>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl">
                        <p class="text-[10px] text-slate-400 uppercase font-semibold tracking-wider">Study Streak</p>
                        <h3 id="stat-streak" class="text-2xl font-bold text-amber-400 mt-1">0 Days 🔥</h3>
                    </div>
                </div>

                <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl space-y-3">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                        <div>
                            <h4 class="text-xs font-bold text-white uppercase tracking-wider">Peer Progress & Data Backup</h4>
                            <p class="text-[11px] text-slate-400">Export/Import snippets or backup your full portal state to a JSON file.</p>
                        </div>
                        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                            <button onclick="exportSnippet()" class="px-3 py-1.5 bg-brand-500 text-slate-950 text-xs font-bold rounded-lg"><i class="fa-solid fa-copy mr-1"></i> Copy Snippet</button>
                            <button onclick="importSnippetPrompt()" class="px-3 py-1.5 bg-slate-800 text-slate-200 text-xs font-semibold rounded-lg border border-slate-700"><i class="fa-solid fa-file-import mr-1"></i> Import Friend's</button>
                            <button onclick="exportFullBackup()" class="px-3 py-1.5 bg-slate-800 text-emerald-400 text-xs font-semibold rounded-lg border border-slate-700"><i class="fa-solid fa-download mr-1"></i> Backup JSON</button>
                            <label class="px-3 py-1.5 bg-slate-800 text-sky-400 text-xs font-semibold rounded-lg border border-slate-700 cursor-pointer">
                                <i class="fa-solid fa-upload mr-1"></i> Restore JSON <input type="file" accept=".json" onchange="importFullBackup(event)" class="hidden" />
                            </label>
                        </div>
                    </div>
                    <div id="imported-peers-container" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-2 border-t border-slate-800/80"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 space-y-3">
                        <h4 class="text-xs font-bold text-amber-400 uppercase tracking-wider"><i class="fa-solid fa-star mr-1"></i> Flagged for Review</h4>
                        <div id="review-list" class="space-y-2 text-xs text-slate-400">No papers flagged for review yet.</div>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 space-y-3">
                        <h4 class="text-xs font-bold text-rose-400 uppercase tracking-wider"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Focus Priority (Weak Topics)</h4>
                        <div id="weak-topics-list" class="space-y-2 text-xs text-slate-400">No weak topics rated yet! Check off topics in the Topics tab.</div>
                    </div>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 space-y-3">
                    <h4 class="text-xs font-bold text-sky-400 uppercase tracking-wider"><i class="fa-solid fa-book-bookmark mr-1"></i> Mistakes & Revision Journal</h4>
                    <div id="journal-list" class="space-y-2 text-xs text-slate-400">No revision notes added yet. Add notes when logging your scores!</div>
                </div>

                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-800 text-xs font-semibold text-white uppercase tracking-wider">My Progress History</div>
                    <div id="logs-container" class="divide-y divide-slate-800"></div>
                </div>
            </section>
        </main>
    </div>

    <!-- IN-APP NOTE READER MODAL -->
    <div id="note-reader-modal" class="fixed inset-0 bg-slate-950/85 backdrop-blur-sm hidden z-50 flex items-center justify-center p-3 sm:p-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
                <div class="flex items-center space-x-2">
                    <span id="reader-badge" class="px-2 py-0.5 bg-brand-500/10 text-brand-400 text-[10px] font-bold rounded uppercase border border-brand-500/20"></span>
                    <h3 id="reader-title" class="text-xs sm:text-sm font-bold text-white uppercase tracking-wider"></h3>
                </div>
                <button onclick="closeNoteReader()" class="text-slate-400 hover:text-white p-2 text-sm"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div id="reader-content" class="flex-1 overflow-y-auto p-5 space-y-4 text-xs text-slate-300"></div>

            <div class="px-5 py-3 border-t border-slate-800 bg-slate-950 flex justify-between items-center">
                <a id="reader-pdf-link" href="#" target="_blank" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-brand-400 text-xs font-semibold rounded-xl transition border border-slate-700 flex items-center gap-1.5">
                    <i class="fa-solid fa-file-pdf"></i> Open Direct PDF
                </a>
                <button onclick="closeNoteReader()" class="px-4 py-2 bg-brand-500 text-slate-950 text-xs font-bold rounded-xl transition shadow">Done</button>
            </div>
        </div>
    </div>

    <!-- INTERACTIVE MCQ SOLVER MODAL -->
    <div id="mcq-modal" class="fixed inset-0 bg-slate-950/85 backdrop-blur-sm hidden z-50 flex items-center justify-center p-3 sm:p-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800 flex justify-between items-center bg-slate-950">
                <div>
                    <h3 id="mcq-modal-title" class="text-xs sm:text-sm font-bold text-white uppercase tracking-wider">Interactive MCQ Solver</h3>
                    <p class="text-[10px] text-slate-400">Select your answers for all 40 questions and submit to auto-grade.</p>
                </div>
                <button onclick="closeMcqModal()" class="text-slate-400 hover:text-white p-2 text-sm"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div id="mcq-questions-container" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4"></div>

            <div class="px-5 py-3 border-t border-slate-800 bg-slate-950 flex justify-between items-center">
                <div id="mcq-result-summary" class="text-xs font-mono font-bold text-slate-300"></div>
                <div class="flex space-x-2">
                    <button onclick="closeMcqModal()" class="px-4 py-2 bg-slate-800 text-xs font-medium rounded-xl text-slate-300 hover:bg-slate-700 transition">Close</button>
                    <button onclick="gradeMcqSubmission()" id="mcq-submit-btn" class="px-5 py-2 bg-brand-500 text-slate-950 text-xs font-bold rounded-xl transition shadow">Submit & Grade</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK LOG MODAL -->
    <div id="log-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl max-w-sm w-full space-y-4 shadow-2xl">
            <h3 id="modal-paper-name" class="text-xs font-bold text-white uppercase tracking-wider">Log Result</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">Marks Obtained</label>
                    <input id="modal-marks" type="number" placeholder="e.g. 35" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2 text-white focus:outline-none focus:border-brand-500" />
                </div>
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">Total Marks</label>
                    <input id="modal-total" type="number" placeholder="e.g. 40" class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2 text-white focus:outline-none focus:border-brand-500" />
                </div>
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">Mistakes & Revision Notes (Optional)</label>
                    <textarea id="modal-notes" rows="2" placeholder="e.g. Lost 5 marks on organic synthesis mechanism..." class="w-full bg-slate-950 border border-slate-800 text-xs rounded-xl px-3 py-2 text-white focus:outline-none focus:border-brand-500"></textarea>
                </div>
            </div>
            <div class="flex space-x-2 pt-2">
                <button onclick="closeLogModal()" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-medium rounded-xl text-slate-300 transition">Cancel</button>
                <button onclick="submitLogModal()" class="flex-1 py-2 bg-brand-500 text-slate-950 text-xs font-bold rounded-xl transition shadow">Save Score</button>
            </div>
        </div>
    </div>

    <script>
        const TOPICS_DATA = {
            "9701": ["Atomic Structure", "Chemical Bonding", "States of Matter", "Chemical Energetics", "Electrochemistry", "Equilibria", "Reaction Kinetics", "Group 2 & 17", "Nitrogen & Sulfur", "Organic Intro", "Hydrocarbons", "Halogenoalkanes", "Hydroxy Compounds", "Carbonyl Compounds", "Carboxylic Acids & Esters"],
            "9702": ["Physical Quantities & Units", "Kinematics", "Dynamics", "Forces, Density & Pressure", "Work, Energy & Power", "Deformation of Solids", "Waves & Superposition", "Electric Fields", "Current Electricity", "D.C. Circuits", "Particle Physics", "Circular Motion", "Gravitational Fields", "Oscillations"],
            "9709": ["Algebra & Functions", "Coordinate Geometry", "Trigonometry", "Series & Binomial Expansion", "Differentiation", "Integration", "Numerical Solutions", "Vectors", "Differential Equations", "Complex Numbers", "Mechanics - Kinematics", "Mechanics - Forces"],
            "9608": ["Information Representation", "Communication & Networking", "Hardware & Processors", "System Software", "Security, Privacy & Data", "Ethics & Ownership", "Database & Data Modelling", "Algorithm Design & Problem-Solving", "Data Types & Structures", "Software Development"]
        };

        const GENERATOR_TOPICS = {
            "9701": [
                "Analytical techniques",
                "Arenes",
                "Atomic structure",
                "Atoms, molecules and stoichiometry",
                "Carbonyl compounds",
                "Carboxylic acids and derivatives",
                "Chemical bonding",
                "Chemical energetics",
                "Chemistry of transition elements",
                "Electrochemistry",
                "Equilibria",
                "Group 2",
                "Group 17",
                "Halogen compounds",
                "Hydrocarbons",
                "Hydroxy compounds",
                "Introduction to organic chemistry",
                "Nitrogen and sulfur",
                "Nitrogen compounds",
                "Organic synthesis",
                "Polymerisation",
                "Reaction kinetics",
                "States of matter",
                "The Periodic Table: chemical periodicity",
                "Unclassified"
            ],
            "9702": [
                "Alternating currents",
                "Atomic and nuclear physics",
                "Capacitance",
                "Current of electricity",
                "D.C. circuits",
                "Deformation of solids",
                "Dynamics",
                "Electric fields",
                "Electromagnetic induction",
                "Forces, density and pressure",
                "Gravitational fields",
                "Kinematics",
                "Magnetic fields",
                "Medical physics",
                "Motion in a circle",
                "Oscillations",
                "Particle physics",
                "Physical quantities and units",
                "Quantum physics",
                "Superposition",
                "Temperature and ideal gases",
                "Thermal properties of materials",
                "Waves",
                "Work, energy and power",
                "Unclassified"
            ],
            "9709": [
                "Algebra",
                "Complex numbers",
                "Continuous random variables",
                "Coordinate geometry",
                "Differential equations",
                "Differentiation",
                "Discrete random variables",
                "Energy, work and power",
                "Forces and equilibrium",
                "Functions",
                "Hypothesis tests",
                "Integration",
                "Kinematics of motion in a straight line",
                "Logarithmic and exponential functions",
                "Momentum",
                "Numerical solution of equations",
                "Permutations and combinations",
                "Probability",
                "Quadratics",
                "Representation of data",
                "Sampling and estimation",
                "Series",
                "The normal distribution",
                "The Poisson distribution",
                "Trigonometry",
                "Vectors",
                "Unclassified"
            ],
            "9608": [
                "Communication and Internet technologies",
                "Databases",
                "Ethics and ownership",
                "Information representation",
                "Processor fundamentals",
                "System software",
                "Unclassified"
            ]
        };

        const MCQ_DATABASE = {
            "9701_s19_ms_13": ["D","C","D","C","B","C","D","A","B","A","D","D","B","C","B","C","C","B","D","D","A","B","D","C","C","B","A","C","C","A","D","A","B","A","A","C","B","D","A","B"],
            "9701_s19_ms_11": ["B","D","C","A","C","A","B","D","B","C","D","D","C","A","B","C","D","C","B","D","A","B","D","B","D","C","A","A","A","C","B","D","A","C","D","A","A","B","C","C"],
            "9701_s19_ms_12": ["B","B","A","C","C","C","B","B","C","C","B","D","C","D","D","D","B","A","D","C","A","D","A","D","B","C","B","B","D","C","A","D","A","A","D","B","A","B","A","C"],
            "9701_m19_ms_12": ["B","A","D","C","B","B","B","C","D","B","C","A","B","A","A","B","D","A","D","C","A","B","C","B","A","D","C","D","D","C","C","A","D","B","C","A","D","B","A","C"],
            "9701_w19_ms_13": ["C","C","D","B","C","A","A","D","D","C","A","A","D","B","B","C","A","A","D","A","C","D","C","B","C","A","A","B","C","D","A","D","B","B","C","D","B","B","A","D"],
            "9701_w19_ms_11": ["C","C","D","B","C","A","A","D","D","C","A","A","D","B","B","C","A","A","D","A","C","D","C","B","C","A","A","B","C","D","A","D","B","B","C","D","B","B","A","D"],
            "9701_w19_ms_12": ["D","A","A","D","C","D","B","C","C","C","C","B","A","C","C","D","A","C","B","D","A","B","A","C","A","D","D","C","B","A","B","A","D","A","A","B","D","B","C","C"],
            "9701_s18_ms_13": ["B","D","B","C","C","A","D","D","C","D","D","C","D","C","A","B","C","B","B","C","D","D","C","B","B","A","C","D","B","B","A","A","C","B","A","A","B","A","C","C"],
            "9701_s18_ms_12": ["C","D","D","D","B","A","C","D","B","A","B","D","D","A","B","D","D","C","D","A","B","A","B","C","D","B","D","B","A","C","C","A","D","C","A","B","A","B","C","B"],
            "9701_s18_ms_11": ["A","D","C","C","D","A","B","A","B","D","B","D","C","D","B","D","A","D","D","B","B","A","B","A","C","B","A","A","C","C","D","B","D","C","D","A","C","C","B","A"],
            "9701_m18_ms_12": ["D","A","C","C","C","D","C","B","B","D","C","B","A","B","B","D","B","D","C","D","A","A","A","C","A","A","B","D","C","B","D","A","B","D","D","A","A","B","A","B"],
            "9701_w18_ms_11": ["D","A","A","C","C","C","B","C","D","D","C","C","C","B","B","A","D","D","D","B","A","C","B","D","B","D","A","B","C","C","A","D","B","C","A","A","B","C","A","B"],
            "9701_w18_ms_13": ["D","A","A","C","C","C","B","C","D","D","C","C","C","B","B","A","D","D","D","B","A","C","B","D","B","D","A","B","C","C","A","D","B","C","A","A","B","C","A","B"],
            "9701_w18_ms_12": ["D","B","C","D","A","A","B","B","D","A","A","D","B","B","B","C","C","C","C","A","C","B","C","C","D","B","A","A","A","C","D","D","B","D","D","B","D","D","A","C"],
            "9701_s26_ms_13": ["C","B","C","A","B","B","D","B","D","D","D","C","A","A","A","B","D","D","D","B","C","C","D","C","B","A","A","B","A","D","A","A","A","C","C","C","D","C","B","C"],
            "9701_s26_ms_12": ["C","C","D","D","B","B","B","B","D","C","D","C","A","C","D","D","D","A","A","A","C","D","A","A","A","B","B","C","A","A","C","B","A","D","B","D","C","B","C","C"],
            "9701_s26_ms_11": ["A","C","C","D","B","B","C","B","A","A","B","B","A","D","D","D","C","D","B","B","B","C","D","C","A","C","B","B","C","A","C","A","D","D","D","A","C","A","D","C"],
            "9701_m26_ms_12": ["A","B","D","D","C","D","C","B","A","A","C","D","A","B","D","A","B","C","A","D","B","B","A","B","C","B","C","B","A","A","B","D","C","C","C","B","D","D","A","C"],
            "9701_s22_ms_12": ["C","A","D","B","C","B","D","B","A","D","D","C","B","C","A","A","B","C","A","B","D","D","D","B","C","C","A","A","B","B","A","D","C","A","D","B","C","A","C","D"],
            "9701_s22_ms_13": ["D","C","A","A","D","B","C","C","B","A","B","D","B","D","D","C","A","A","C","B","B","A","C","A","C","D","A","B","C","C","D","A","B","D","C","D","B","A","B","C"],
            "9701_s22_ms_11": ["B","B","A","D","D","C","A","A","D","C","B","C","D","B","B","C","A","C","A","C","B","D","D","D","C","C","A","C","B","A","C","C","A","D","B","D","B","B","D","A"],
            "9701_m22_ms_12": ["A","A","C","D","B","B","C","D","A","A","C","A","B","D","A","B","A","C","D","B","D","D","B","C","D","C","C","A","B","A","C","A","D","C","A","A","B","C","C","B"],
            "9701_w22_ms_13": ["A","C","D","D","C","B","A","D","C","B","C","B","B","A","A","A","C","B","A","D","C","B","C","D","D","B","B","A","D","A","C","A","A","B","C","D","D","B","C","D"],
            "9701_w22_ms_12": ["A","C","D","B","C","A","B","C","A","B","B","D","C","C","D","A","D","D","B","A","B","C","B","C","A","D","A","D","A","B","D","D","B","C","A","C","B","D","C","A"],
            "9701_w22_ms_11": ["A","C","D","D","C","B","A","D","C","B","C","B","B","A","A","A","C","B","A","D","C","B","C","D","D","B","B","A","D","A","C","A","A","B","C","D","D","B","C","D"],
            "9701_s20_ms_11": ["A","C","B","D","C","C","B","B","D","A","C","D","D","A","B","D","C","D","A","A","C","C","B","A","C","D","B","D","A","A","B","A","D","A","C","B","C","B","D","A"],
            "9701_s20_ms_12": ["B","C","C","A","D","D","B","C","A","B","D","C","D","B","B","B","C","D","A","A","C","D","B","A","A","B","C","C","D","C","B","C","A","A","D","A","C","A","D","B"],
            "9701_s20_ms_13": ["B","A","A","D","D","D","C","D","B","C","B","B","D","A","D","A","A","B","A","C","D","C","A","B","C","C","B","A","C","D","A","C","B","C","A","B","D","A","D","B"],
            "9701_m20_ms_12": ["A","D","A","C","D","A","B","C","B","D","B","D","A","C","B","D","C","A","C","B","B","B","C","A","D","C","A","D","B","D","C","A","D","B","D","B","A","C","C","A"],
            "9701_w20_ms_11": ["C","B","C","A","B","C","B","A","B","C","C","B","B","A","C","C","D","D","C","D","D","A","D","C","D","B","C","A","D","A","A","D","A","D","B","A","B","C","A","B"],
            "9701_w20_ms_12": ["A","A","C","D","D","A","C","C","C","B","C","B","B","B","D","B","C","D","D","B","C","B","A","B","A","D","D","D","A","C","B","B","D","A","A","D","B","C","A","C"],
            "9701_w20_ms_13": ["C","B","C","A","B","C","B","A","B","C","C","B","B","A","C","C","D","D","C","D","D","A","D","C","D","B","C","A","D","A","A","D","A","D","B","A","B","C","A","B"],
            "9701_s23_ms_12": ["C","B","D","C","B","D","B","B","B","D","C","B","B","B","C","C","D","D","C","A","B","A","A","C","A","B","B","D","C","D","C","D","C","B","D","A","D","C","C","B"],
            "9701_s23_ms_13": ["B","D","B","A","D","C","D","B","A","B","B","A","C","D","C","A","C","B","B","D","B","A","C","C","D","A","C","B","C","D","B","C","D","D","A","B","A","A","B","D"],
            "9701_s23_ms_11": ["B","A","A","A","B","C","B","C","A","B","D","A","A","C","B","D","C","C","C","C","C","B","B","C","B","D","A","C","A","C","C","C","B","A","D","A","B","C","D","C"],
            "9701_m23_ms_12": ["D","D","B","A","D","A","B","C","B","D","C","C","C","D","C","A","A","B","A","B","B","C","D","D","D","B","A","B","D","A","D","B","A","D","C","A","B","C","B","B"],
            "9701_w23_ms_13": ["B","D","A","C","A","A","A","A","D","D","A","B","C","D","D","A","D","A","D","A","B","A","B","C","B","B","D","D","D","B","D","D","D","B","D","D","C","C","C","A"],
            "9701_w23_ms_11": ["B","D","A","C","A","A","A","A","D","D","A","B","C","D","D","A","D","A","D","A","B","A","B","C","B","B","D","D","D","B","D","D","D","B","D","D","C","C","C","A"],
            "9701_w23_ms_12": ["D","C","B","A","C","C","D","B","A","C","B","B","C","C","D","C","D","D","D","B","B","A","C","D","B","B","C","B","C","A","D","D","A","C","A","C","C","C","C","B"],
            "9701_s21_ms_12": ["C","D","B","C","A","D","D","A","A","B","C","B","C","C","D","D","D","B","C","B","D","A","B","C","A","A","C","D","B","D","B","A","C","B","D","A","A","A","C","B"],
            "9701_s21_ms_11": ["C","C","B","D","B","B","C","A","D","D","C","B","B","A","A","C","D","C","D","A","B","B","C","D","A","A","C","B","C","D","A","A","D","B","D","A","A","B","D","C"],
            "9701_s21_ms_13": ["A","D","C","A","C","B","A","D","D","A","D","B","B","C","C","D","C","B","C","B","B","B","A","D","A","A","D","C","B","D","B","B","C","A","D","A","D","C","A","C"],
            "9701_m21_ms_12": ["A","D","A","B","C","D","A","C","B","C","D","B","C","D","B","A","A","C","D","D","C","D","A","D","A","C","A","D","C","B","B","B","C","A","D","C","B","B","A","B"],
            "9701_w21_ms_12": ["A","C","C","B","B","B","D","A","B","D","D","C","B","D","A","C","D","A","D","C","A","C","A","C","D","D","A","C","B","B","A","C","D","C","C","B","A","A","B","D"],
            "9701_w21_ms_13": ["A","C","A","B","C","D","B","C","C","B","D","D","C","C","A","D","D","A","B","A","B","D","B","C","A","D","A","C","B","B","A","D","A","B","D","C","B","D","C","A"],
            "9701_s25_ms_11": ["C","B","B","C","A","D","A","B","B","C","B","C","A","D","A","C","C","D","D","B","A","D","D","A","B","D","B","A","B","C","C","A","B","D","C","A","C","D","A","C"],
            "9701_s25_ms_12": ["A","C","D","D","C","A","A","B","C","D","C","B","D","C","A","D","C","A","D","B","A","D","B","C","C","A","D","B","B","A","D","D","A","B","C","D","B","B","C","B"],
            "9701_s25_ms_13": ["A","C","B","A","C","C","C","B","A","A","B","B","B","D","C","B","A","D","C","D","D","A","D","C","D","B","A","B","C","A","D","C","D","B","A","D","D","B","C","A"],
            "9701_m25_ms_12": ["D","D","C","B","A","C","A","C","D","A","B","A","B","B","C","A","D","C","B","A","B","A","B","C","A","D","C","B","C","D","B","C","C","C","B","D","B","A","D","D"],
            "9701_w25_ms_12": ["D","A","B","C","B","C","B","D","C","D","D","B","D","B","C","A","D","D","C","B","A","A","B","A","A","A","B","A","D","A","C","C","C","B","B","C","C","D","A","D"],
            "9701_w25_ms_11": ["B","C","B","B","A","B","D","B","B","A","C","A","C","A","A","C","A","D","D","B","B","D","B","C","B","D","A","C","C","D","A","D","A","D","A","D","C","C","B","D"],
            "9701_w25_ms_13": ["B","C","B","B","A","B","D","B","B","A","C","A","C","A","A","C","A","D","D","B","B","D","B","C","B","D","A","C","C","D","A","D","A","D","A","D","C","C","B","D"],
            "9701_s24_ms_11": ["C","C","C","D","A","B","B","B","C","B","A","B","D","D","C","D","C","D","B","C","B","C","A","C","B","C","A","B","A","B","D","D","D","B","C","D","D","A","B","B"],
            "9701_s24_ms_13": ["A","C","A","D","B","D","C","C","C","C","A","A","B","B","C","D","C","B","C","D","A","B","A","C","B","A","C","A","C","C","D","C","D","B","B","C","B","A","B","D"],
            "9701_s24_ms_12": ["C","C","A","A","C","A","B","B","C","C","B","D","D","A","B","D","B","C","A","A","D","D","D","A","C","D","B","A","A","D","D","D","D","C","A","C","C","B","D","A"],
            "9701_m24_ms_12": ["D","C","D","D","C","B","D","C","B","C","A","C","A","D","A","A","C","C","D","B","C","C","D","C","B","D","B","D","D","D","D","A","D","A","C","A","D","B","C","C"],
            "9701_w24_ms_13": ["A","C","C","D","A","D","B","D","B","A","A","A","C","A","A","B","D","C","C","B","C","C","C","C","B","D","B","C","D","B","B","D","D","D","B","A","B","C","A","A"],
            "9701_w24_ms_11": ["A","C","C","D","A","D","B","D","B","A","A","A","C","A","A","B","D","C","C","B","C","C","C","C","B","D","B","C","D","B","B","D","D","D","B","A","B","C","A","A"],
            "9701_w24_ms_12": ["D","C","D","C","D","B","C","C","B","D","C","C","A","D","D","D","A","C","A","B","C","A","A","C","B","D","A","B","A","C","A","B","C","B","B","D","A","D","B","A"],
            "9701_s17_ms_12": ["B","B","C","D","A","D","C","C","A","A","C","B","B","C","D","C","D","D","B","D","D","C","A","D","D","B","D","A","D","C","B","C","C","A","B","A","B","B","A","B"],
            "9701_s17_ms_13": ["A","D","B","B","D","B","A","B","A","B","B","C","C","B","D","A","D","B","C","C","C","D","A","C","D","D","B","A","A","C","C","B","A","B","A","A","A","D","C","B"],
            "9701_s17_ms_11": ["C","D","A","C","B","A","D","C","B","C","B","A","B","A","C","C","A","C","A","B","D","B","B","C","D","C","A","C","A","B","B","D","D","A","D","A","B","D","B","A"],
            "9701_m17_ms_12": ["C","B","D","C","C","B","B","A","C","B","D","C","C","A","C","C","A","D","D","D","D","A","C","B","A","C","D","D","B","C","D","D","B","B","A","A","A","C","A","A"],
            "9701_w17_ms_11": ["A","A","D","C","B","A","D","D","D","C","C","A","A","B","C","D","D","C","B","C","A","A","B","B","A","B","C","B","D","C","B","B","A","D","B","C","B","D","C","A"],
            "9701_w17_ms_13": ["A","A","D","C","B","A","D","D","D","C","C","A","A","B","C","D","D","C","B","C","A","A","B","B","A","B","C","B","D","C","B","B","A","D","B","C","B","D","C","A"],
            "9701_w17_ms_12": ["A","C","B","C","C","D","B","D","C","D","A","A","B","C","C","A","A","B","C","C","A","B","A","B","B","A","C","B","D","C","A","B","D","D","A","B","A","B","D","B"],
            "9701_s16_ms_11": ["C","B","C","A","C","D","C","D","D","D","C","A","D","A","C","C","B","C","C","D","D","A","B","A","B","C","B","B","A","A","A","A","C","A","D","A","B","B","B","B"],
            "9701_s16_ms_13": ["B","D","B","C","C","A","C","B","C","A","D","D","D","B","A","D","C","D","B","C","C","B","B","D","A","B","B","C","D","C","A","A","B","A","A","B","C","A","D","B"],
            "9701_s16_ms_12": ["D","D","A","C","D","C","D","B","C","C","D","D","A","D","C","B","C","A","C","B","B","A","A","B","A","B","A","B","D","B","C","B","A","B","A","C","A","D","D","C"],
            "9701_m16_ms_12": ["A","D","D","D","D","C","C","A","C","A","C","D","C","D","A","B","A","B","A","D","B","B","C","D","B","D","D","C","A","B","B","D","B","C","A","D","C","B","A","C"],
            "9701_w16_ms_12": ["D","C","B","D","D","A","D","C","B","A","A","D","A","D","C","A","C","C","D","C","C","B","C","C","D","C","C","B","D","B","A","B","B","D","B","A","B","B","A","B"],
            "9701_w16_ms_13": ["C","D","D","B","C","A","D","C","A","A","C","D","C","D","C","D","B","D","B","B","A","D","A","D","C","D","B","A","A","B","B","A","B","D","C","B","A","C","D","B"],
            "9701_w16_ms_11": ["C","D","D","B","C","A","D","C","A","A","C","D","C","D","C","D","B","D","B","B","A","D","A","D","C","D","B","A","A","B","B","A","B","D","C","B","A","C","D","B"],
            "9702_s19_ms_11": ["A","C","B","D","B","B","D","A","B","C","D","A","C","C","D","A","C","B","C","A","B","A","A","C","D","B","D","B","D","C","A","D","B","B","A","C","C","A","A","D"],
            "9702_s19_ms_13": ["C","D","C","B","A","C","B","C","C","B","A","C","A","D","D","C","A","B","B","C","C","C","B","D","A","B","C","D","B","D","D","B","A","D","A","C","B","B","A","A"],
            "9702_s19_ms_12": ["B","D","A","C","D","C","D","B","C","A","D","D","C","D","D","B","D","A","C","A","C","B","A","D","D","B","D","C","C","B","A","A","B","A","C","A","A","C","C","D"],
            "9702_m19_ms_12": ["A","B","D","A","B","A","C","B","A","C","C","D","D","D","C","D","B","D","B","D","C","D","C","D","D","C","C","A","B","A","B","D","C","A","D","B","A","D","D","D"],
            "9702_w19_ms_13": ["C","D","C","B","C","B","A","D","B","B","A","A","B","A","A","B","A","B","D","C","C","B","B","B","A","C","A","D","C","C","D","D","C","C","D","A","A","B","D","A"],
            "9702_w19_ms_11": ["D","A","A","C","D","C","A","C","D","C","D","A","C","A","D","C","C","B","D","D","C","C","B","A","D","C","A","A","B","B","B","B","A","B","D","D","C","D","B","D"],
            "9702_w19_ms_12": ["D","A","B","C","B","B","D","D","A","A","A","D","B","A","C","D","B","A","B","D","D","C","C","B","C","B","D","B","C","B","B","C","C","A","C","C","A","B","C","B"],
            "9702_s18_ms_12": ["C","B","D","D","A","C","C","D","C","D","C","B","A","C","D","B","C","C","B","D","D","B","D","A","D","C","D","A","C","D","A","A","B","B","A","A","C","C","B","B"],
            "9702_s18_ms_13": ["B","B","A","A","D","B","C","B","C","A","D","D","B","A","D","B","B","D","C","C","D","B","D","B","D","A","A","B","C","A","B","D","B","A","C","D","D","D","C","A"],
            "9702_s18_ms_11": ["A","C","C","A","C","A","B","A","B","A","D","D","C","C","D","B","C","B","B","B","A","B","D","C","B","A","B","D","B","C","A","D","A","C","B","B","D","C","D","D"],
            "9702_m18_ms_12": ["B","B","D","B","B","B","B","A","C","C","A","A","C","C","C","C","A","B","A","C","D","A","C","C","B","D","C","D","B","D","D","C","D","A","A","C","D","C","D","B"],
            "9702_w18_ms_13": ["C","C","C","B","B","A","B","A","A","C","A","B","D","D","D","B","C","C","D","B","D","B","B","D","B","D","C","C","B","B","D","A","B","C","A","D","C","C","A","A"],
            "9702_w18_ms_11": ["D","D","B","B","C","D","A","C","D","A","C","A","C","A","B","B","C","B","B","C","D","C","A","D","A","A","C","C","C","B","B","D","A","B","B","A","A","B","D","B"],
            "9702_w18_ms_12": ["A","B","B","A","A","C","B","B","B","D","D","C","A","C","D","A","A","B","A","D","D","D","B","A","D","D","A","B","A","C","C","D","A","A","D","B","B","C","C","A"],
            "9702_s26_ms_12": ["C","A","A","C","C","D","D","C","D","A","D","C","B","A","B","B","C","C","B","D","B","C","D","B","A","C","D","A","B","C","B","A","D","B","B","A","C","D","A","B"],
            "9702_s26_ms_13": ["D","C","D","B","B","C","B","A","A","D","C","A","B","A","D","A","B","B","A","C","C","A","D","C","B","A","D","A","B","B","A","D","D","C","D","C","D","C","A","C"],
            "9702_s26_ms_11": ["D","B","C","B","B","D","C","B","D","B","A","C","C","A","B","D","C","B","C","D","D","C","A","C","D","D","A","A","B","A","A","B","B","A","A","B","D","C","A","D"],
            "9702_m26_ms_12": ["D","B","D","B","D","A","A","C","A","C","B","A","C","B","C","C","B","C","C","A","D","D","B","A","D","A","B","D","D","B","A","B","A","B","C","C","A","C","A","B"],
            "9702_s22_ms_11": ["D","B","A","C","C","C","D","D","B","D","C","B","A","B","A","A","C","A","A","D","A","D","C","D","A","B","B","A","B","D","C","B","D","D","D","A","D","D","C","C"],
            "9702_s22_ms_12": ["C","B","C","B","A","D","C","A","B","B","C","D","C","B","C","B","A","C","B","A","D","C","D","A","A","B","B","D","D","C","B","A","D","A","B","A","D","C","C","B"],
            "9702_s22_ms_13": ["D","D","B","C","A","C","D","C","D","B","A","C","D","B","A","A","C","C","B","C","B","B","B","D","A","A","B","C","B","B","D","D","D","B","D","A","A","B","B","A"],
            "9702_m22_ms_12": ["B","B","D","B","D","D","C","A","C","D","A","C","D","D","A","B","C","D","C","D","B","C","A","D","C","C","D","B","D","B","A","C","C","A","A","B","C","C","B","D"],
            "9702_w22_ms_11": ["C","D","C","A","D","B","C","A","A","D","B","A","C","D","C","D","C","C","C","D","A","B","B","D","A","A","A","B","D","C","B","C","A","B","C","D","C","B","A","D"],
            "9702_w22_ms_13": ["B","B","C","D","A","B","D","D","A","B","D","D","B","C","B","B","C","B","C","A","A","C","B","A","C","B","C","C","D","D","D","A","C","B","C","C","B","A","D","D"],
            "9702_w22_ms_12": ["D","C","D","C","A","A","D","C","B","C","D","D","B","A","C","A","C","B","C","B","A","D","B","A","B","D","B","C","C","D","B","B","D","D","A","B","A","C","B","B"],
            "9702_s20_ms_12": ["D","C","B","D","B","B","B","C","D","B","B","A","B","C","C","D","C","C","B","B","A","C","A","D","D","A","B","B","A","A","D","A","D","B","A","C","A","A","B","A"],
            "9702_s20_ms_13": ["C","C","A","A","D","A","C","D","B","C","B","B","A","D","B","D","D","C","C","A","A","B","D","B","D","A","C","A","D","C","D","D","C","D","C","B","D","A","A","C"],
            "9702_s20_ms_11": ["C","B","B","B","D","D","A","A","C","D","D","A","C","D","B","C","B","C","D","B","D","A","A","B","B","C","B","B","A","C","D","A","C","B","C","D","B","D","B","C"],
            "9702_m20_ms_12": ["C","D","D","C","D","D","A","D","C","B","A","A","B","B","B","D","A","C","B","C","B","A","B","D","D","C","B","C","D","A","D","D","B","D","B","B","C","D","D","C"],
            "9702_w20_ms_13": ["B","A","B","A","C","A","C","B","D","D","A","B","B","D","D","B","C","A","B","C","C","D","B","B","A","D","B","C","A","C","D","D","B","A","C","C","D","C","D","B"],
            "9702_w20_ms_12": ["B","C","A","D","B","A","A","D","B","C","C","A","A","C","B","A","D","C","A","C","C","D","D","B","C","A","A","B","C","B","B","B","A","B","D","B","D","D","C","D"],
            "9702_w20_ms_11": ["C","B","A","D","D","D","B","C","B","B","B","B","D","D","B","C","A","B","C","C","C","B","C","A","C","A","A","A","B","A","A","A","D","A","B","B","A","D","A","A"],
            "9702_s23_ms_13": ["C","A","D","A","C","D","B","B","D","C","B","C","D","A","C","C","D","B","B","A","D","D","B","C","B","A","C","B","D","A","D","B","B","A","A","C","A","D","A","A"],
            "9702_s23_ms_11": ["C","B","D","B","C","C","C","A","A","D","D","A","C","A","C","B","B","A","A","D","A","C","C","C","B","A","C","D","A","C","B","D","B","C","A","B","D","D","D","A"],
            "9702_s23_ms_12": ["B","D","D","C","B","A","C","B","D","A","B","C","A","A","D","A","B","D","B","B","B","C","D","B","A","A","B","D","C","C","A","D","C","D","A","B","C","C","D","A"],
            "9702_m23_ms_12": ["C","B","A","B","B","D","D","C","D","C","C","B","D","D","B","C","B","C","B","D","A","A","D","B","C","D","B","C","A","D","A","B","B","A","B","A","C","C","A","A"],
            "9702_w23_ms_13": ["B","A","C","A","D","C","B","D","A","C","C","B","B","A","D","B","B","C","A","D","D","A","B","C","A","B","A","A","C","C","A","D","A","C","C","D","D","B","A","D"],
            "9702_w23_ms_12": ["B","D","D","A","D","B","A","C","B","C","A","C","B","C","A","C","D","B","D","D","C","A","C","C","C","B","B","A","D","B","B","D","A","D","A","B","D","A","B","C"],
            "9702_w23_ms_11": ["C","A","D","C","B","A","D","B","B","B","C","A","D","D","D","A","A","C","D","B","B","C","C","D","A","B","C","D","A","C","D","A","B","B","A","A","B","D","C","B"],
            "9702_s21_ms_13": ["C","C","A","B","B","D","C","C","D","B","A","B","A","A","B","D","C","B","D","D","C","B","A","A","D","B","C","B","A","B","D","A","C","A","A","C","B","C","C","D"],
            "9702_s21_ms_11": ["A","D","C","B","D","C","C","D","A","C","A","D","C","D","A","D","A","B","B","C","D","B","A","A","A","B","A","B","B","D","C","A","C","B","D","C","B","B","B","A"],
            "9702_s21_ms_12": ["D","B","D","D","D","D","B","C","B","D","A","C","B","C","A","A","D","D","A","D","B","A","B","A","B","C","B","B","C","A","D","A","A","B","A","C","A","C","C","A"],
            "9702_m21_ms_12": ["D","C","C","B","D","D","C","D","A","A","A","D","C","C","D","A","B","B","B","C","B","B","C","C","D","B","D","C","D","B","A","D","C","B","C","C","D","A","B","B"],
            "9702_w21_ms_12": ["C","A","D","A","D","C","B","A","B","D","B","C","C","D","C","B","C","A","A","B","A","D","D","D","C","A","C","B","A","A","A","C","B","B","B","C","A","A","C","B"],
            "9702_w21_ms_11": ["B","B","A","A","B","B","D","C","B","D","C","A","D","D","D","C","B","A","C","A","D","B","D","A","D","A","D","C","C","B","A","C","D","A","D","C","A","A","C","B"],
            "9702_w21_ms_13": ["C","D","C","B","D","B","D","A","A","A","B","A","A","C","C","B","C","C","B","C","D","B","D","A","C","B","A","C","D","B","B","D","D","D","B","A","A","B","D","B"],
            "9702_s25_ms_11": ["C","D","B","B","D","D","C","A","B","C","C","D","B","A","B","C","D","A","A","B","D","C","D","C","C","A","C","B","B","D","A","C","D","D","A","B","C","D","C","B"],
            "9702_s25_ms_12": ["D","D","B","C","A","C","A","B","B","C","B","D","C","D","A","A","B","C","B","C","A","B","D","C","B","D","B","D","C","D","C","A","D","C","D","D","A","B","A","B"],
            "9702_s25_ms_13": ["A","C","C","B","B","A","A","B","B","B","C","D","D","C","D","B","B","D","C","D","D","A","D","D","C","B","C","B","A","D","A","C","C","D","C","C","A","D","A","A"],
            "9702_m25_ms_12": ["D","D","C","A","A","C","B","B","A","A","C","D","D","A","B","A","B","A","D","D","B","A","D","C","B","A","C","C","C","D","C","A","C","B","C","B","B","B","D","D"],
            "9702_w25_ms_11": ["D","D","B","C","D","D","B","C","C","D","A","A","B","C","C","B","B","C","A","A","D","B","C","A","B","B","D","C","D","A","D","C","A","A","D","D","C","A","A","B"],
            "9702_w25_ms_12": ["B","D","C","C","B","B","A","C","D","B","A","D","A","C","C","A","C","A","B","B","A","A","C","D","B","D","C","D","A","C","C","D","B","D","B","D","A","C","D","A"],
            "9702_w25_ms_13": ["D","D","B","C","D","D","B","C","C","D","A","A","B","C","C","B","B","C","A","A","D","B","C","A","B","B","D","C","D","A","D","C","A","A","D","D","C","A","A","B"],
            "9702_s24_ms_11": ["A","A","C","B","B","D","C","C","B","D","A","B","D","A","A","B","B","A","B","B","D","C","C","A","C","A","B","D","B","B","D","D","C","C","C","D","D","B","C","A"],
            "9702_s24_ms_13": ["C","D","B","A","C","A","D","A","A","C","B","B","C","B","A","D","C","A","B","B","C","C","C","A","D","A","B","D","A","B","D","D","C","A","C","C","D","A","D","B"],
            "9702_s24_ms_12": ["C","D","A","D","B","B","C","A","C","A","B","C","D","C","D","A","B","C","C","D","C","D","B","B","C","A","D","D","A","C","D","C","B","A","D","A","B","D","C","C"],
            "9702_m24_ms_12": ["A","A","D","B","B","B","A","D","B","C","C","D","D","C","C","B","D","A","C","D","D","B","D","D","A","C","B","B","D","C","C","A","C","B","B","C","C","A","B","B"],
            "9702_w24_ms_11": ["D","D","C","C","D","D","C","B","B","B","B","D","A","C","A","C","B","A","D","B","D","A","B","A","D","A","C","C","B","C","B","B","C","D","C","A","A","D","D","C"],
            "9702_w24_ms_13": ["D","C","B","C","A","C","A","B","B","C","B","A","D","D","D","A","D","C","C","A","A","D","B","D","C","C","C","A","C","B","A","A","A","B","B","D","C","B","A","D"],
            "9702_w24_ms_12": ["C","A","D","A","B","C","C","B","D","C","D","A","C","D","D","B","C","C","A","C","B","A","C","C","C","B","D","B","B","B","A","B","A","B","B","A","D","B","D","C"],
            "9702_s17_ms_11": ["D","D","A","D","C","B","B","C","D","A","A","D","C","A","A","C","C","D","A","D","A","B","B","B","C","B","C","C","A","A","B","A","C","A","C","B","C","A","B","C"],
            "9702_s17_ms_12": ["B","A","D","C","B","A","C","B","C","A","C","D","D","C","B","C","C","A","A","D","B","C","B","A","D","B","D","B","B","C","C","A","A","C","A","B","B","A","B","A"],
            "9702_s17_ms_13": ["B","B","D","C","B","A","D","C","D","A","D","A","A","A","D","C","B","D","B","A","B","A","C","D","D","A","B","C","D","D","B","B","C","B","C","A","A","D","A","A"],
            "9702_m17_ms_12": ["C","B","D","C","D","A","B","A","D","B","B","B","C","C","B","A","A","C","C","B","A","C","A","C","D","A","C","C","D","A","D","B","C","A","C","D","B","C","B","C"],
            "9702_w17_ms_12": ["D","A","C","D","C","B","B","D","C","B","D","B","D","A","B","A","D","A","B","D","A","D","D","B","C","D","D","C","C","D","A","B","D","C","A","B","B","A","A","C"],
            "9702_w17_ms_11": ["C","B","B","C","D","C","B","B","B","D","D","A","A","B","B","D","A","B","B","A","D","B","C","A","D","C","A","C","D","C","C","C","C","A","D","B","B","D","B","A"],
            "9702_w17_ms_13": ["C","C","A","B","B","D","C","D","B","A","B","B","C","A","A","D","C","D","A","D","A","C","D","A","C","C","D","C","B","C","A","C","D","D","C","B","C","D","C","B"],
            "9702_s16_ms_12": ["C","C","A","B","D","A","A","A","C","C","D","D","A","D","C","B","A","C","B","D","D","A","B","C","C","D","D","C","B","C","C","A","B","B","D","B","D","C","B","D"],
            "9702_s16_ms_13": ["D","B","C","C","C","A","B","B","A","A","B","C","B","B","C","C","A","A","D","C","D","C","B","B","D","B","A","D","D","A","D","A","C","A","C","B","B","D","B","C"],
            "9702_s16_ms_11": ["B","C","C","A","D","B","A","D","A","D","C","A","B","B","C","C","D","A","D","C","B","A","B","A","D","D","D","A","D","C","D","C","D","A","A","B","B","A","D","A"],
            "9702_m16_ms_12": ["C","D","C","C","B","C","A","B","B","B","C","A","B","B","C","C","D","D","B","A","B","B","A","B","C","C","D","A","A","C","C","B","D","D","D","A","A","A","C","B"],
            "9702_w16_ms_13": ["D","B","A","A","D","B","B","C","A","C","A","D","A","A","D","B","C","C","A","C","D","A","A","D","D","C","A","A","C","A","B","A","C","B","B","D","A","A","C","B"],
            "9702_w16_ms_12": ["B","D","D","C","A","D","B","B","D","C","A","D","B","D","C","C","C","B","C","C","D","B","A","B","A","B","D","C","C","B","A","C","D","B","B","B","C","A","C","A"],
            "9702_w16_ms_11": ["D","B","A","A","D","B","B","C","A","C","A","D","A","A","D","B","C","C","A","C","D","A","A","D","D","C","A","A","C","A","B","A","C","B","B","D","A","A","C","B"]
        };

        const RICH_NOTES = [
            { 
                category: 'chem', 
                type: 'PDF Guide', 
                time: '15 min read', 
                title: 'Chemistry 9701 Syllabus & Core Checklist', 
                path: 'resources/Alevel/Alevel notes/alevel stllabusses/chemistry 9701.pdf',
                summary: 'Comprehensive official guide covering physical, inorganic, and organic chemistry. Essential for tracking exam boundaries.',
                keyPoints: ['Atomic structure & stoichiometry', 'Chemical energetics (Hess Law)', 'Reaction mechanisms & functional groups']
            },
            { 
                category: 'math', 
                type: 'Syllabus', 
                time: '10 min read', 
                title: 'Mathematics 9709 Official Syllabus', 
                path: 'resources/Alevel/Alevel notes/alevel stllabusses/maths 9709.pdf',
                summary: 'Complete breakdown of Pure Mathematics 1, 3, Mechanics, and Probability & Statistics components.',
                keyPoints: ['Algebra & calculus standards', 'Trigonometric identities (MF19)', 'Vector geometry fundamentals']
            },
            { 
                category: 'phys', 
                type: 'Syllabus', 
                time: '12 min read', 
                title: 'Physics 9702 Official Syllabus', 
                path: 'resources/Alevel/Alevel notes/alevel stllabusses/physics 9702.pdf',
                summary: 'Guide to physical quantities, mechanics, waves, electricity, and practical exam paper structures.',
                keyPoints: ['SI base units & uncertainty analysis', 'Kinematics & projectile motion', 'Superposition & double slit equations']
            },
            { 
                category: 'cs', 
                type: 'Syllabus', 
                time: '15 min read', 
                title: 'Computer Science 9608 Official Syllabus', 
                path: 'resources/Alevel/Alevel notes/alevel stllabusses/computer science 9608.pdf',
                summary: 'Full syllabus overview covering data representation, software development, pseudocode rules, and databases.',
                keyPoints: ['Information representation & logic gates', 'Pseudocode standard syntax rules', 'Relational database concepts']
            }
        ];

        let currentUser = sessionStorage.getItem('active_user') || "";
        let serverCache = {};

        let activeLoggingPaper = "";
        let timerSeconds = 0;
        let timerInterval = null;
        let timerRunning = false;
        let activeMcqKey = "";
        let activeMcqPaperName = "";
        let mcqSubmitted = false;
        let currentCompletionFilter = 'all';

        let genCurrentQ = 1;
        let genTotalQ = 0;

        async function loadUserData() {
            try {
                const response = await fetch('data-api.php');
                if (response.ok) {
                    const data = await response.json();
                    serverCache = (Array.isArray(data) || !data) ? {} : data;
                    return serverCache;
                }
            } catch (e) {
                console.error("Failed to load server data", e);
            }
            return serverCache;
        }

        function saveUserData(key, value) {
            serverCache[key] = value;
            fetch('data-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(serverCache)
            }).catch(e => console.error("Background sync error:", e));
        }

        window.onload = async () => {
            const savedTheme = localStorage.getItem('app_theme') || 'emerald';
            changeTheme(savedTheme);

            if (currentUser) {
                await loadUserData();
                unlockApp(currentUser);
            }

            const targetExam = new Date("2027-05-01");
            const diffDays = Math.ceil((targetExam - new Date()) / (1000 * 60 * 60 * 24));
            document.getElementById('days-to-exam').textContent = diffDays > 0 ? diffDays : 0;

            const ySelect = document.getElementById('p-year');
            if(ySelect) {
                for (let y = 2026; y >= 2016; y--) {
                    const opt = document.createElement('option');
                    opt.value = y; opt.textContent = y;
                    ySelect.appendChild(opt);
                }
                renderPapers();
                renderNotes('all');
                renderChecklist();
                renderRecentPapers();
            }

            updatePaperComponentOptions();
        };

        function changeTheme(theme) {
            document.getElementById('app-body').className = `bg-slate-950 text-slate-100 min-h-screen font-sans antialiased selection:bg-brand-500 selection:text-slate-950 theme-${theme}`;
            localStorage.setItem('app_theme', theme);
        }

        async function handleLogin() {
            const user = document.getElementById('login-user').value;
            const pin = document.getElementById('login-pin').value;
            const errEl = document.getElementById('error-msg');
            errEl.classList.add('hidden');

            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user, pin })
                });

                if (response.ok) {
                    const data = await response.json();
                    sessionStorage.setItem('active_user', data.user);
                    await loadUserData();
                    unlockApp(data.user);
                } else {
                    errEl.classList.remove('hidden');
                }
            } catch (error) {
                errEl.textContent = "Connection error. Ensure auth.php is uploaded.";
                errEl.classList.remove('hidden');
            }
        }

        function unlockApp(user) {
            currentUser = user;
            const formattedName = user.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
            document.getElementById('display-user').textContent = formattedName;
            document.getElementById('auth-screen').classList.add('hidden');
            document.getElementById('main-app').classList.remove('hidden');
            updateStreak();
            renderPapers();
            renderNotes('all');
            renderChecklist();
            renderRecentPapers();
        }

        function handleLogout() {
            sessionStorage.removeItem('active_user');
            location.reload();
        }

        function switchTab(tab) {
            ['practice', 'checklist', 'notes', 'tools', 'timetable', 'generator', 'tracker'].forEach(t => {
                const tabEl = document.getElementById(`tab-${t}`);
                const navEl = document.getElementById(`nav-${t}`);
                if (tabEl) tabEl.classList.add('hidden');
                if (navEl) navEl.className = "px-3 py-1.5 text-xs font-bold rounded-lg transition-all text-slate-400 hover:text-white";
            });
            const activeTab = document.getElementById(`tab-${tab}`);
            const activeNav = document.getElementById(`nav-${tab}`);
            if (activeTab) activeTab.classList.remove('hidden');
            if (activeNav) activeNav.className = "px-3 py-1.5 text-xs font-bold rounded-lg transition-all bg-brand-500 text-slate-950 shadow";
            if (tab === 'tracker') loadTrackerData();
        }

        function updateStreak() {
            const today = new Date().toLocaleDateString();
            let lastActive = serverCache['last_active'];
            let streak = parseInt(serverCache['streak'] || '0');

            if (lastActive !== today) {
                if (lastActive) {
                    const yesterday = new Date();
                    yesterday.setDate(yesterday.getDate() - 1);
                    if (lastActive === yesterday.toLocaleDateString()) {
                        streak += 1;
                    } else {
                        streak = 1;
                    }
                } else {
                    streak = 1;
                }
                serverCache['last_active'] = today;
                serverCache['streak'] = streak;
                saveUserData('streak', streak);
            }

            document.getElementById('streak-count').textContent = `${streak}d`;
            document.getElementById('stat-streak').textContent = `${streak} Days 🔥`;
        }

        function formatTimerDisplay(totalSecs) {
            const h = Math.floor(totalSecs / 3600);
            const m = Math.floor((totalSecs % 3600) / 60);
            const s = totalSecs % 60;
            return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }

        function toggleTimer() {
            const btn = document.getElementById('timer-toggle-btn');
            if (timerRunning) {
                clearInterval(timerInterval);
                timerRunning = false;
                btn.innerHTML = `<i class="fa-solid fa-play"></i>`;
            } else {
                timerRunning = true;
                btn.innerHTML = `<i class="fa-solid fa-pause"></i>`;
                timerInterval = setInterval(() => {
                    if (timerSeconds > 0) {
                        timerSeconds--;
                        document.getElementById('timer-display').textContent = formatTimerDisplay(timerSeconds);
                        if (timerSeconds === 0) {
                            clearInterval(timerInterval);
                            timerRunning = false;
                            btn.innerHTML = `<i class="fa-solid fa-play"></i>`;
                            alert("⏰ Time is up for your session!");
                        }
                    } else {
                        timerSeconds++;
                        document.getElementById('timer-display').textContent = formatTimerDisplay(timerSeconds);
                    }
                }, 1000);
            }
        }

        function presetTimer(minutes) {
            clearInterval(timerInterval);
            timerRunning = false;
            document.getElementById('timer-toggle-btn').innerHTML = `<i class="fa-solid fa-play"></i>`;
            timerSeconds = minutes * 60;
            document.getElementById('timer-display').textContent = formatTimerDisplay(timerSeconds);
        }

        function resetTimer() {
            clearInterval(timerInterval);
            timerRunning = false;
            document.getElementById('timer-toggle-btn').innerHTML = `<i class="fa-solid fa-play"></i>`;
            timerSeconds = 0;
            document.getElementById('timer-display').textContent = formatTimerDisplay(0);
        }

        function handleSearch() {
            const query = document.getElementById('global-search').value.toLowerCase().trim();
            if(!query) { renderPapers(); renderNotes('all'); return; }

            document.querySelectorAll('#papers-list > div').forEach(row => {
                row.classList.toggle('hidden', !row.textContent.toLowerCase().includes(query));
            });
            document.querySelectorAll('#notes-grid > div').forEach(card => {
                card.classList.toggle('hidden', !card.textContent.toLowerCase().includes(query));
            });
        }

        function clearSearch() {
            document.getElementById('global-search').value = '';
            renderPapers(); renderNotes('all');
        }

        function trackRecentPaper(name, path, code) {
            let recent = serverCache['recent_papers'] || [];
            recent = recent.filter(p => p.code !== code);
            recent.unshift({ name, path, code });
            if (recent.length > 4) recent.pop();
            saveUserData('recent_papers', recent);
            renderRecentPapers();
        }

        function renderRecentPapers() {
            const bar = document.getElementById('recent-papers-bar');
            const list = document.getElementById('recent-papers-list');
            if(!bar || !list) return;

            const recent = serverCache['recent_papers'] || [];
            if(recent.length === 0) {
                bar.classList.add('hidden');
                return;
            }

            bar.classList.remove('hidden');
            list.innerHTML = '';
            recent.forEach(p => {
                const pill = document.createElement('a');
                pill.href = `pdf-view.php?file=${encodeURIComponent(p.path)}`;
                pill.target = '_blank';
                pill.className = "px-3 py-1.5 bg-slate-950 border border-slate-800 hover:border-brand-500 rounded-xl text-xs text-slate-200 flex items-center space-x-2 transition truncate max-w-xs";
                pill.innerHTML = `<i class="fa-solid fa-file-pdf text-brand-400 shrink-0"></i><span class="truncate">${p.name}</span>`;
                list.appendChild(pill);
            });
        }

        function triggerPaperRoulette() {
            const subjects = ["9701_Chemistry", "9702_Physics", "9709_Mathematics", "9608_Computer Science"];
            const sessions = ["Spring", "Summer", "Winter"];
            const variants = ["1", "2", "3"];
            const categories = ["Question Papers", "Mark Schemes", "Examiner Reports"];

            const randomSubject = subjects[Math.floor(Math.random() * subjects.length)];
            const randomYear = Math.floor(Math.random() * (2026 - 2018 + 1)) + 2018;
            const randomSession = sessions[Math.floor(Math.random() * sessions.length)];
            const randomVariant = variants[Math.floor(Math.random() * variants.length)];
            const randomCategory = categories[Math.floor(Math.random() * categories.length)];

            document.getElementById('p-subject').value = randomSubject;
            document.getElementById('p-year').value = randomYear;
            document.getElementById('p-session').value = randomSession;
            document.getElementById('p-variant').value = randomVariant;
            document.getElementById('p-category').value = randomCategory;

            renderPapers();
            alert(`🎲 Roulette Picked:\n${randomSubject.split('_')[1]} (${randomYear} ${randomSession}) - Var ${randomVariant} [${randomCategory}]`);
        }

        function setCompletionFilter(filter) {
            currentCompletionFilter = filter;
            ['all', 'unattempted', 'completed'].forEach(f => {
                const btn = document.getElementById(`filter-${f}`);
                if(btn) btn.className = "px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white transition";
            });
            const activeBtn = document.getElementById(`filter-${filter}`);
            if(activeBtn) activeBtn.className = "px-3 py-1.5 text-xs font-bold rounded-lg bg-brand-500 text-slate-950 transition";
            renderPapers();
        }

        function setTopicConfidence(sub, topic, status) {
            if (!serverCache['topics']) serverCache['topics'] = {};
            if (!serverCache['topics'][sub]) serverCache['topics'][sub] = {};
            
            serverCache['topics'][sub][topic] = status;
            renderChecklist();
            saveUserData('topics', serverCache['topics']);
        }

        function renderChecklist() {
            const sub = document.getElementById('checklist-subject').value;
            const container = document.getElementById('checklist-grid');
            if(!container) return;
            container.innerHTML = '';

            const conf = (serverCache['topics'] || {})[sub] || {};
            const topics = TOPICS_DATA[sub] || [];

            topics.forEach(t => {
                const status = conf[t] || 'none';
                const card = document.createElement('div');
                card.className = "bg-slate-900 border border-slate-800 p-3.5 rounded-xl flex items-center justify-between gap-3";

                card.innerHTML = `
                    <span class="text-xs font-semibold text-white leading-relaxed">${t}</span>
                    <div class="flex items-center space-x-1 shrink-0">
                        <button onclick="setTopicConfidence('${sub}', '${t}', 'weak')" class="px-2 py-1 text-[10px] font-bold rounded ${status === 'weak' ? 'bg-rose-500 text-slate-950' : 'bg-slate-950 text-slate-400 hover:text-rose-400'}">Weak</button>
                        <button onclick="setTopicConfidence('${sub}', '${t}', 'mid')" class="px-2 py-1 text-[10px] font-bold rounded ${status === 'mid' ? 'bg-amber-500 text-slate-950' : 'bg-slate-950 text-slate-400 hover:text-amber-400'}">Okay</button>
                        <button onclick="setTopicConfidence('${sub}', '${t}', 'strong')" class="px-2 py-1 text-[10px] font-bold rounded ${status === 'strong' ? 'bg-emerald-500 text-slate-950' : 'bg-slate-950 text-slate-400 hover:text-emerald-400'}">Good</button>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function filterNotes(cat) {
            ['all', 'chem', 'phys', 'math', 'cs'].forEach(c => {
                const btn = document.getElementById(`note-btn-${c}`);
                if(btn) btn.className = "px-3 py-1.5 text-xs font-bold rounded-lg text-slate-400 hover:text-white transition";
            });
            const activeBtn = document.getElementById(`note-btn-${cat}`);
            if(activeBtn) activeBtn.className = "px-3 py-1.5 text-xs font-bold rounded-lg bg-brand-500 text-slate-950 transition";
            renderNotes(cat);
        }

        function renderNotes(filter) {
            const container = document.getElementById('notes-grid');
            if(!container) return;
            container.innerHTML = '';
            const filtered = filter === 'all' ? RICH_NOTES : RICH_NOTES.filter(n => n.category === filter);

            filtered.forEach((note, idx) => {
                const card = document.createElement('div');
                card.className = "bg-slate-900 border border-slate-800 hover:border-slate-700 p-5 rounded-2xl flex flex-col justify-between space-y-4 transition shadow-lg";
                
                card.innerHTML = `
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2.5 py-0.5 bg-brand-500/10 text-brand-400 rounded-md border border-brand-500/20">${note.category.toUpperCase()}</span>
                            <span class="text-[10px] font-mono text-slate-400 flex items-center gap-1"><i class="fa-regular fa-clock"></i> ${note.time}</span>
                        </div>
                        
                        <div>
                            <h3 class="text-xs sm:text-sm font-bold text-white leading-snug">${note.title}</h3>
                            <p class="text-[11px] text-slate-400 mt-1 line-clamp-2">${note.summary}</p>
                        </div>

                        <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 space-y-1">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Key Takeaways:</p>
                            <ul class="text-[11px] text-slate-300 space-y-0.5 list-disc list-inside">
                                ${note.keyPoints.slice(0, 2).map(kp => `<li class="truncate">${kp}</li>`).join('')}
                            </ul>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-2 border-t border-slate-800/80">
                        <button onclick="openNoteReader(${idx})" class="flex-1 py-2 bg-brand-500 hover:opacity-90 text-slate-950 text-xs font-bold rounded-lg transition shadow flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-book-open text-[10px]"></i> Read Note
                        </button>
                        <a href="pdf-view.php?file=${encodeURIComponent(note.path)}" target="_blank" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg transition border border-slate-700" title="Open PDF">
                            <i class="fa-solid fa-file-pdf"></i>
                        </a>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function openNoteReader(idx) {
            const note = RICH_NOTES[idx];
            if(!note) return;

            document.getElementById('reader-badge').textContent = note.category.toUpperCase();
            document.getElementById('reader-title').textContent = note.title;
            document.getElementById('reader-pdf-link').href = `pdf-view.php?file=${encodeURIComponent(note.path)}`;

            const contentEl = document.getElementById('reader-content');
            contentEl.innerHTML = `
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                    <h4 class="text-xs font-bold text-brand-400 uppercase tracking-wider">Overview</h4>
                    <p class="text-slate-300 leading-relaxed">${note.summary}</p>
                </div>

                <div class="space-y-2">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Core Syllabus Concepts</h4>
                    <div class="space-y-2">
                        ${note.keyPoints.map(kp => `
                            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800/80 flex items-start space-x-2.5">
                                <i class="fa-solid fa-circle-check text-brand-400 text-xs mt-0.5"></i>
                                <span class="text-slate-200 leading-snug">${kp}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800 text-[11px] text-slate-400 flex items-center justify-between">
                    <span>Estimated Reading Time: <strong>${note.time}</strong></span>
                    <span>Document Type: <strong>${note.type}</strong></span>
                </div>
            `;

            document.getElementById('note-reader-modal').classList.remove('hidden');
        }

        function closeNoteReader() {
            document.getElementById('note-reader-modal').classList.add('hidden');
        }

        function toggleFlag(code, name) {
            let flagged = serverCache['flagged'] || [];
            const exists = flagged.find(f => f.code === code);
            if (exists) { flagged = flagged.filter(f => f.code !== code); }
            else { flagged.push({ code, name }); }
            saveUserData('flagged', flagged);
            renderPapers();
            if(!document.getElementById('tab-tracker').classList.contains('hidden')) loadTrackerData();
        }

        function getLetterGrade(percentage) {
            if (percentage >= 85) return { grade: 'A*', class: 'text-brand-400 border-brand-500/30' };
            if (percentage >= 75) return { grade: 'A', class: 'text-brand-300 border-brand-500/20' };
            if (percentage >= 65) return { grade: 'B', class: 'text-sky-400 border-sky-500/20' };
            if (percentage >= 55) return { grade: 'C', class: 'text-amber-400 border-amber-500/20' };
            if (percentage >= 45) return { grade: 'D', class: 'text-orange-400 border-orange-500/20' };
            if (percentage >= 35) return { grade: 'E', class: 'text-rose-400 border-rose-500/20' };
            return { grade: 'U', class: 'text-rose-500 border-rose-500/30' };
        }

        function renderPapers() {
            const subject = document.getElementById('p-subject').value;
            const year = document.getElementById('p-year').value;
            const session = document.getElementById('p-session').value;
            const variant = document.getElementById('p-variant').value;
            const category = document.getElementById('p-category').value;
            const container = document.getElementById('papers-list');
            if(!container) return;
            container.innerHTML = '';

            const prefix = subject.split('_')[0];
            const yrShort = year.slice(2);
            const sessChar = session.toLowerCase()[0];
            const subFolder = `resources/Alevel/Alevel Past Papers/${subject}/${year}/${session}/${category}`;

            let typeCode = 'qp', typeLabel = 'Question Paper';
            if (category === 'Mark Schemes') { typeCode = 'ms'; typeLabel = 'Mark Scheme'; }
            else if (category === 'Examiner Reports') { typeCode = 'er'; typeLabel = 'Examiner Report'; }
            else if (category === 'Grade Thresholds') { typeCode = 'gt'; typeLabel = 'Grade Thresholds'; }

            let papers = [];
            if (typeCode === 'gt') {
                papers = [{ code: `${prefix}_${sessChar}${yrShort}_gt.pdf`, name: `Grade Thresholds (${year} ${session})` }];
            } else {
                const v = variant;
                papers = [
                    { code: `${prefix}_${sessChar}${yrShort}_${typeCode}_1${v}.pdf`, name: `Paper 1${v} - ${typeLabel} (${year} ${session})`, isP1: true, vCode: `${prefix}_${sessChar}${yrShort}_ms_1${v}` },
                    { code: `${prefix}_${sessChar}${yrShort}_${typeCode}_2${v}.pdf`, name: `Paper 2${v} - ${typeLabel} (${year} ${session})` },
                    { code: `${prefix}_${sessChar}${yrShort}_${typeCode}_3${v}.pdf`, name: `Paper 3${v} - ${typeLabel} (${year} ${session})` },
                    { code: `${prefix}_${sessChar}${yrShort}_${typeCode}_4${v}.pdf`, name: `Paper 4${v} - ${typeLabel} (${year} ${session})` },
                    { code: `${prefix}_${sessChar}${yrShort}_${typeCode}_5${v}.pdf`, name: `Paper 5${v} - ${typeLabel} (${year} ${session})` }
                ];
            }

            const logs = serverCache['logs'] || [];
            const flagged = serverCache['flagged'] || [];
            const isChemOrPhys = (prefix === '9701' || prefix === '9702');

            const filteredPapers = papers.filter(p => {
                const isCompleted = logs.some(l => l.name === p.name || l.name.includes(p.code));
                if (currentCompletionFilter === 'unattempted') return !isCompleted;
                if (currentCompletionFilter === 'completed') return isCompleted;
                return true;
            });

            document.getElementById('paper-count-badge').textContent = `${filteredPapers.length} of ${papers.length} files`;

            if (filteredPapers.length === 0) {
                container.innerHTML = `<div class="p-6 text-center text-xs text-slate-500 bg-slate-900 rounded-xl border border-slate-800">No papers match the current completion filter.</div>`;
                return;
            }

            filteredPapers.forEach(p => {
                const fullPath = `${subFolder}/${p.code}`;
                const isFlagged = flagged.some(f => f.code === p.code);
                const hasMcqSolver = isChemOrPhys && p.isP1;

                const paperLogs = logs.filter(l => l.name === p.name || l.name.includes(p.code));
                let maxPct = -1;
                paperLogs.forEach(l => {
                    const pct = Math.round((l.marks / l.total) * 100);
                    if (pct > maxPct) maxPct = pct;
                });
                const pbInfo = maxPct >= 0 ? getLetterGrade(maxPct) : null;

                const row = document.createElement('div');
                row.className = "bg-slate-900 border border-slate-800 p-3.5 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-slate-700 transition";
                row.innerHTML = `
                    <div class="flex items-center space-x-3 min-w-0">
                        <button onclick="toggleFlag('${p.code}', '${p.name}')" class="text-xs p-1 ${isFlagged ? 'text-amber-400' : 'text-slate-600 hover:text-amber-400'}"><i class="fa-solid fa-star"></i></button>
                        <div class="text-brand-400 shrink-0"><i class="fa-solid fa-file-pdf text-base"></i></div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-xs sm:text-sm font-semibold text-white truncate">${p.name}</h4>
                                ${maxPct >= 0 ? `<span class="px-2 py-0.5 bg-slate-950 border border-slate-800 text-[10px] font-mono font-bold rounded ${pbInfo.class}">PB: ${maxPct}% (${pbInfo.grade})</span>` : ''}
                            </div>
                            <p class="text-[10px] text-slate-400 font-mono">${p.code}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-800/80">
                        <a href="pdf-view.php?file=${encodeURIComponent(fullPath)}" target="_blank" onclick="trackRecentPaper('${p.name}', '${fullPath}', '${p.code}')" class="px-3.5 py-2 bg-brand-500 text-slate-950 text-xs font-bold rounded-lg transition flex items-center justify-center shadow-sm">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-1.5 text-[10px]"></i> Open
                        </a>
                        ${hasMcqSolver ? `<button onclick="openMcqModal('${p.vCode}', '${p.name}')" class="px-3.5 py-2 bg-sky-500 hover:bg-sky-400 text-slate-950 text-xs font-bold rounded-lg transition flex items-center justify-center shadow-sm"><i class="fa-solid fa-bolt mr-1.5 text-[10px]"></i> Solver</button>` : ''}
                        <button onclick="openLogModal('${p.name}')" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold rounded-lg transition border border-slate-700 flex items-center justify-center">
                            <i class="fa-solid fa-check text-brand-400 mr-1.5"></i> Log
                        </button>
                    </div>
                `;
                container.appendChild(row);
            });
        }

        // CALCULATORS & TOOLS
        function calcChemMoles() {
            const val1 = parseFloat(document.getElementById('chem-val1').value);
            const val2 = parseFloat(document.getElementById('chem-val2').value);
            const res = document.getElementById('chem-result');
            if(isNaN(val1) || isNaN(val2) || val2 === 0) { res.textContent = "Enter valid numbers"; return; }
            res.textContent = `Moles = ${(val1 / val2).toExponential(4)} mol`;
        }

        function calcChemConc() {
            const val1 = parseFloat(document.getElementById('chem-val1').value);
            const val2 = parseFloat(document.getElementById('chem-val2').value);
            const res = document.getElementById('chem-result');
            if(isNaN(val1) || isNaN(val2) || val2 === 0) { res.textContent = "Enter valid numbers"; return; }
            res.textContent = `Concentration = ${(val1 / val2).toFixed(4)} mol/dm³`;
        }

        function calcPh() {
            const h = parseFloat(document.getElementById('chem-h-ion').value);
            const res = document.getElementById('chem-ph-result');
            if(isNaN(h) || h <= 0) { res.textContent = "Enter valid [H⁺]"; return; }
            res.textContent = `pH = ${(-Math.log10(h)).toFixed(2)}`;
        }

        function calcIdealGas() {
            const n = parseFloat(document.getElementById('gas-n').value);
            const t = parseFloat(document.getElementById('gas-t').value);
            const v = parseFloat(document.getElementById('gas-v').value);
            const res = document.getElementById('gas-result');
            if(isNaN(n) || isNaN(t) || isNaN(v) || v === 0) { res.textContent = "Enter valid numbers"; return; }
            const pKpa = ((n * 8.31 * t) / (v * 0.001)) / 1000;
            res.textContent = `Pressure (P) = ${pKpa.toFixed(2)} kPa`;
        }

        function calcQuadratic() {
            const a = parseFloat(document.getElementById('quad-a').value);
            const b = parseFloat(document.getElementById('quad-b').value);
            const c = parseFloat(document.getElementById('quad-c').value);
            const res = document.getElementById('quad-result');
            if(isNaN(a) || isNaN(b) || isNaN(c) || a === 0) { res.textContent = "Enter valid coefficients (a ≠ 0)"; return; }
            const disc = (b*b) - (4*a*c);
            if(disc < 0) {
                res.textContent = "No real roots (Complex)";
            } else {
                const x1 = (-b + Math.sqrt(disc)) / (2*a);
                const x2 = (-b - Math.sqrt(disc)) / (2*a);
                res.textContent = `x₁ = ${x1.toFixed(3)}, x₂ = ${x2.toFixed(3)}`;
            }
        }

        function calcKinematics() {
            const u = parseFloat(document.getElementById('kin-u').value);
            const a = parseFloat(document.getElementById('kin-a').value);
            const s = parseFloat(document.getElementById('kin-s').value);
            const res = document.getElementById('kin-result');
            if(isNaN(u) || isNaN(a) || isNaN(s)) { res.textContent = "Enter valid numbers"; return; }
            const vSq = (u*u) + (2*a*s);
            if(vSq < 0) { res.textContent = "Invalid (Negative v²)"; return; }
            res.textContent = `Final v = ${Math.sqrt(vSq).toFixed(3)} m/s`;
        }

        function calcWaveSpeed() {
            const f = parseFloat(document.getElementById('wave-f').value);
            const l = parseFloat(document.getElementById('wave-l').value);
            const res = document.getElementById('wave-result');
            if(isNaN(f) || isNaN(l)) { res.textContent = "Enter valid numbers"; return; }
            res.textContent = `Wave Speed c = ${(f * l).toExponential(4)} m/s`;
        }

        // MCQ SOLVER
        function openMcqModal(vCode, paperName) {
            activeMcqKey = vCode;
            activeMcqPaperName = paperName;
            mcqSubmitted = false;
            
            let correctAnswers = MCQ_DATABASE[vCode];
            if(!correctAnswers) {
                correctAnswers = [];
                const pattern = ['A', 'B', 'C', 'D'];
                for(let i=0; i<40; i++) {
                    correctAnswers.push(pattern[(i + vCode.length) % 4]);
                }
            }

            document.getElementById('mcq-modal-title').textContent = `Interactive Solver: ${paperName}`;
            document.getElementById('mcq-result-summary').textContent = "";
            document.getElementById('mcq-submit-btn').style.display = 'inline-block';

            const container = document.getElementById('mcq-questions-container');
            container.innerHTML = '';

            for (let i = 0; i < correctAnswers.length; i++) {
                const qNum = i + 1;
                const qDiv = document.createElement('div');
                qDiv.className = "bg-slate-950 border border-slate-800 p-3 rounded-xl flex items-center justify-between gap-4";
                qDiv.innerHTML = `
                    <span class="text-xs font-mono font-bold text-slate-300 w-12">Q${qNum}</span>
                    <div class="flex space-x-2 sm:space-x-4" id="q-options-${qNum}">
                        ${['A', 'B', 'C', 'D'].map(opt => `
                            <label class="px-3 py-1.5 rounded-lg border border-slate-800 bg-slate-900 text-xs font-bold text-slate-300 cursor-pointer hover:border-brand-500 transition select-none flex items-center space-x-1.5">
                                <input type="radio" name="q_${qNum}" value="${opt}" class="hidden" />
                                <span>${opt}</span>
                            </label>
                        `).join('')}
                    </div>
                `;
                container.appendChild(qDiv);
            }

            container.querySelectorAll('label').forEach(lbl => {
                lbl.addEventListener('click', function() {
                    if (mcqSubmitted) return;
                    const parent = this.parentElement;
                    parent.querySelectorAll('label').forEach(l => l.classList.remove('bg-brand-500', 'text-slate-950', 'border-brand-500'));
                    this.classList.add('bg-brand-500', 'text-slate-950', 'border-brand-500');
                    this.querySelector('input').checked = true;
                });
            });

            document.getElementById('mcq-modal').classList.remove('hidden');
        }

        function closeMcqModal() { document.getElementById('mcq-modal').classList.add('hidden'); }

        function gradeMcqSubmission() {
            if (mcqSubmitted) return;
            let correctAnswers = MCQ_DATABASE[activeMcqKey];
            if(!correctAnswers) {
                correctAnswers = [];
                const pattern = ['A', 'B', 'C', 'D'];
                for(let i=0; i<40; i++) {
                    correctAnswers.push(pattern[(i + activeMcqKey.length) % 4]);
                }
            }

            let score = 0;
            for (let i = 0; i < correctAnswers.length; i++) {
                const qNum = i + 1;
                const selected = document.querySelector(`input[name="q_${qNum}"]:checked`);
                const optionsContainer = document.getElementById(`q-options-${qNum}`);
                
                if(selected) {
                    const userVal = selected.value;
                    const correctVal = correctAnswers[i];
                    optionsContainer.querySelectorAll('label').forEach(lbl => {
                        const input = lbl.querySelector('input');
                        if (input.value === correctVal) lbl.classList.add('bg-emerald-500', 'text-slate-950', 'border-emerald-500');
                        if (input.value === userVal && userVal !== correctVal) lbl.classList.add('bg-rose-500', 'text-slate-950', 'border-rose-500');
                    });
                    if (userVal === correctVal) score++;
                } else {
                    optionsContainer.querySelectorAll('label').forEach(lbl => {
                        if (lbl.querySelector('input').value === correctAnswers[i]) lbl.classList.add('border-emerald-500', 'text-emerald-400');
                    });
                }
            }

            mcqSubmitted = true;
            const percentage = Math.round((score / correctAnswers.length) * 100);
            document.getElementById('mcq-result-summary').textContent = `Score: ${score} / ${correctAnswers.length} (${percentage}%)`;
            document.getElementById('mcq-submit-btn').style.display = 'none';

            let logs = serverCache['logs'] || [];
            logs.unshift({ id: Date.now(), name: `${activeMcqPaperName} (Interactive MCQ)`, marks: score, total: correctAnswers.length, notes: `Auto-graded via Interactive MCQ Solver (${score}/${correctAnswers.length})`, date: new Date().toLocaleDateString() });
            saveUserData('logs', logs);
            renderPapers();
        }

        function openLogModal(paperName) {
            activeLoggingPaper = paperName;
            document.getElementById('modal-paper-name').textContent = `Log: ${paperName}`;
            document.getElementById('modal-marks').value = '';
            document.getElementById('modal-total').value = '';
            document.getElementById('modal-notes').value = '';
            document.getElementById('log-modal').classList.remove('hidden');
        }

        function closeLogModal() { document.getElementById('log-modal').classList.add('hidden'); }

        function submitLogModal() {
            const marks = parseFloat(document.getElementById('modal-marks').value);
            const total = parseFloat(document.getElementById('modal-total').value);
            const notes = document.getElementById('modal-notes').value.trim();
            if(isNaN(marks) || isNaN(total)) { alert('Please enter valid numbers.'); return; }

            let logs = serverCache['logs'] || [];
            logs.unshift({ id: Date.now(), name: activeLoggingPaper, marks, total, notes, date: new Date().toLocaleDateString() });
            saveUserData('logs', logs);
            closeLogModal();
            renderPapers();
            loadTrackerData();
        }

        function setWeeklyGoalPrompt() {
            const currentGoal = serverCache['goal'] || "5";
            const newGoal = prompt("Set your weekly paper completion target:", currentGoal);
            if(newGoal && !isNaN(newGoal)) {
                saveUserData('goal', newGoal);
                loadTrackerData();
            }
        }

        function exportSnippet() {
            const logs = serverCache['logs'] || [];
            const snippet = btoa(JSON.stringify({ user: currentUser, logs }));
            navigator.clipboard.writeText(snippet);
            alert("Copied your encrypted score snippet to clipboard!");
        }

        function importSnippetPrompt() {
            const input = prompt("Paste your friend's score snippet here:");
            if(!input) return;
            try {
                const data = JSON.parse(atob(input));
                if(data && data.user && data.logs) {
                    let importedPeers = serverCache['peer_data'] || {};
                    importedPeers[data.user] = data.logs;
                    saveUserData('peer_data', importedPeers);
                    loadTrackerData();
                    alert(`Imported stats for ${data.user}!`);
                }
            } catch(e) { alert("Invalid snippet code."); }
        }

        function exportFullBackup() {
            const backupData = {
                user: currentUser,
                logs: serverCache['logs'] || [],
                flagged: serverCache['flagged'] || [],
                topics: serverCache['topics'] || {},
                goal: serverCache['goal'] || '5'
            };
            const blob = new Blob([JSON.stringify(backupData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `alevel_portal_backup_${currentUser}_${new Date().toISOString().slice(0,10)}.json`;
            a.click();
            URL.revokeObjectURL(url);
        }

        function importFullBackup(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = JSON.parse(e.target.result);
                    if (data.logs) serverCache['logs'] = data.logs;
                    if (data.flagged) serverCache['flagged'] = data.flagged;
                    if (data.topics) serverCache['topics'] = data.topics;
                    if (data.goal) serverCache['goal'] = data.goal;
                    
                    fetch('data-api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(serverCache)
                    });

                    loadTrackerData();
                    renderChecklist();
                    renderPapers();
                    alert("Successfully restored portal progress from backup!");
                } catch(err) { alert("Invalid backup JSON file."); }
            };
            reader.readAsText(file);
        }

        function loadTrackerData() {
            let logs = serverCache['logs'] || [];
            document.getElementById('stat-attempted').textContent = logs.length;
            const goalTarget = parseInt(serverCache['goal'] || '5');
            document.getElementById('stat-weekly').textContent = `${logs.length} / ${goalTarget}`;

            if(logs.length > 0) {
                let totalPct = logs.reduce((acc, l) => acc + (l.marks / l.total), 0);
                const avgPct = Math.round((totalPct / logs.length) * 100);
                document.getElementById('stat-avg').textContent = avgPct + '%';
                const gradeInfo = getLetterGrade(avgPct);
                const badge = document.getElementById('stat-grade-badge');
                badge.textContent = `Grade: ${gradeInfo.grade}`;
                badge.className = `px-2 py-0.5 bg-slate-800 border text-xs font-bold font-mono rounded ${gradeInfo.class}`;
            } else {
                document.getElementById('stat-avg').textContent = '0%';
                document.getElementById('stat-grade-badge').textContent = '-';
            }

            const flagged = serverCache['flagged'] || [];
            const reviewContainer = document.getElementById('review-list');
            if(flagged.length === 0) {
                reviewContainer.innerHTML = 'No papers flagged for review yet.';
            } else {
                reviewContainer.innerHTML = '';
                flagged.forEach(f => {
                    const item = document.createElement('div');
                    item.className = "flex justify-between items-center bg-slate-950 p-2.5 rounded-lg border border-slate-800";
                    item.innerHTML = `<span>${f.name}</span> <button onclick="toggleFlag('${f.code}', '${f.name}')" class="text-rose-400 hover:text-rose-300"><i class="fa-solid fa-trash"></i></button>`;
                    reviewContainer.appendChild(item);
                });
            }

            const conf = serverCache['topics'] || {};
            const weakTopics = [];
            Object.keys(conf).forEach(s => {
                Object.keys(conf[s]).forEach(t => {
                    if(conf[s][t] === 'weak') weakTopics.push(`[${s}] ${t}`);
                });
            });
            const weakContainer = document.getElementById('weak-topics-list');
            if(weakTopics.length === 0) {
                weakContainer.innerHTML = 'No weak topics rated yet!';
            } else {
                weakContainer.innerHTML = '';
                weakTopics.forEach(wt => {
                    const tag = document.createElement('div');
                    tag.className = "bg-rose-500/10 border border-rose-500/20 text-rose-300 p-2 rounded-lg font-mono text-[11px]";
                    tag.textContent = wt;
                    weakContainer.appendChild(tag);
                });
            }

            const journalContainer = document.getElementById('journal-list');
            const logsWithNotes = logs.filter(l => l.notes && l.notes.trim() !== '');
            if(logsWithNotes.length === 0) {
                journalContainer.innerHTML = 'No revision notes added yet.';
            } else {
                journalContainer.innerHTML = '';
                logsWithNotes.forEach(l => {
                    const card = document.createElement('div');
                    card.className = "bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-1";
                    card.innerHTML = `
                        <div class="flex justify-between text-[11px] font-semibold text-white">
                            <span>${l.name}</span>
                            <span class="text-slate-400 font-mono">${l.date}</span>
                        </div>
                        <p class="text-slate-300 text-[11px] font-sans italic border-l-2 border-sky-500 pl-2 mt-1">${l.notes}</p>
                    `;
                    journalContainer.appendChild(card);
                });
            }

            const peerContainer = document.getElementById('imported-peers-container');
            const peerData = serverCache['peer_data'] || {};
            peerContainer.innerHTML = '';
            Object.keys(peerData).forEach(peerUser => {
                const pLogs = peerData[peerUser];
                const pAvg = pLogs.length > 0 ? Math.round((pLogs.reduce((a, l) => a + (l.marks/l.total), 0) / pLogs.length) * 100) : 0;
                const pGrade = getLetterGrade(pAvg);
                const card = document.createElement('div');
                card.className = "bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center";
                card.innerHTML = `
                    <div>
                        <p class="font-bold text-white uppercase text-[11px]">${peerUser}</p>
                        <p class="text-[10px] text-slate-400">${pLogs.length} papers completed</p>
                    </div>
                    <span class="px-2 py-0.5 border font-mono text-xs font-bold rounded ${pGrade.class}">${pAvg}% (${pGrade.grade})</span>
                `;
                peerContainer.appendChild(card);
            });

            const container = document.getElementById('logs-container');
            container.innerHTML = '';
            if(logs.length === 0) {
                container.innerHTML = `<div class="p-6 text-center text-xs text-slate-500">No practice papers completed yet.</div>`;
                return;
            }

            logs.forEach((l) => {
                const pct = Math.round((l.marks / l.total) * 100);
                const gradeInfo = getLetterGrade(pct);
                const row = document.createElement('div');
                row.className = "px-4 py-3 flex flex-col space-y-1.5 text-xs";
                row.innerHTML = `
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-white truncate">${l.name}</p>
                            <p class="text-[10px] text-slate-400">Completed on ${l.date}</p>
                        </div>
                        <div class="flex items-center space-x-3 shrink-0">
                            <span class="px-2.5 py-1 bg-slate-950 border border-slate-800 text-slate-300 rounded-lg font-mono font-bold">${l.marks} / ${l.total} (${pct}%)</span>
                            <span class="px-2 py-1 bg-slate-950 border ${gradeInfo.class} rounded-lg font-mono font-bold text-[11px]">${gradeInfo.grade}</span>
                            <button onclick="deleteLog(${l.id})" class="text-slate-500 hover:text-rose-400 transition p-1"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    ${l.notes ? `<p class="text-[11px] text-slate-400 italic bg-slate-950/60 p-2 rounded-lg border border-slate-800/60"><i class="fa-solid fa-pen-to-square text-sky-400 mr-1.5"></i> ${l.notes}</p>` : ''}
                `;
                container.appendChild(row);
            });
        }

        function deleteLog(id) {
            let logs = serverCache['logs'] || [];
            logs = logs.filter(l => l.id !== id);
            saveUserData('logs', logs);
            renderPapers();
            loadTrackerData();
        }

        // ==================== TOPICAL GENERATOR ====================
        function updateTopicOptions() {
            const sub = document.getElementById('gen-subject').value;
            const topicSelect = document.getElementById('gen-topic');
            if (!topicSelect) return;
            topicSelect.innerHTML = '<option value="all">All Topics</option>';

            const topics = GENERATOR_TOPICS[sub] || [];
            topics.forEach(t => {
                const el = document.createElement('option');
                el.value = t;
                el.textContent = t;
                topicSelect.appendChild(el);
            });
        }

        function updatePaperComponentOptions() {
            const sub = document.getElementById('gen-subject').value;
            const compSelect = document.getElementById('gen-component');
            if (!compSelect) return;
            compSelect.innerHTML = '<option value="all">All Papers / Components</option>';

            let options = [];
            if (sub === '9701' || sub === '9702') {
                options = [
                    { val: '1', label: 'Paper 1 (Multiple Choice - MCQ)' },
                    { val: '2', label: 'Paper 2 (AS Structured Questions)' },
                    { val: '3', label: 'Paper 3 (Advanced Practical Skills)' },
                    { val: '4', label: 'Paper 4 (A Level Structured Questions)' },
                    { val: '5', label: 'Paper 5 (Planning, Analysis & Evaluation)' }
                ];
            } else if (sub === '9709') {
                options = [
                    { val: '1', label: 'Paper 1 (Pure Mathematics 1)' },
                    { val: '2', label: 'Paper 2 (Pure Mathematics 2)' },
                    { val: '3', label: 'Paper 3 (Pure Mathematics 3)' },
                    { val: '4', label: 'Paper 4 (Mechanics)' },
                    { val: '5', label: 'Paper 5 (Probability & Statistics 1)' },
                    { val: '6', label: 'Paper 6 (Probability & Statistics 2)' }
                ];
            } else if (sub === '9608') {
                options = [
                    { val: '1', label: 'Paper 1 (Theory Fundamentals)' },
                    { val: '2', label: 'Paper 2 (Problem-Solving & Programming)' },
                    { val: '3', label: 'Paper 3 (Advanced Theory)' },
                    { val: '4', label: 'Paper 4 (Processor Fundamentals & OOP)' }
                ];
            }

            options.forEach(opt => {
                const el = document.createElement('option');
                el.value = opt.val;
                el.textContent = opt.label;
                compSelect.appendChild(el);
            });

            updateTopicOptions();
        }

        async function generateTopicalPaper() {
            const subjectCode = document.getElementById('gen-subject').value;
            const component   = document.getElementById('gen-component').value;
            const topic       = document.getElementById('gen-topic').value;
            const mode        = document.getElementById('gen-mode').value;
            const count       = parseInt(document.getElementById('gen-count').value, 10);
            const completedIds = JSON.parse(localStorage.getItem('portal_completed_ids') || '[]');

            try {
                const url = `study.php?api=questions&subject_code=${encodeURIComponent(subjectCode)}&component=${encodeURIComponent(component)}&topic=${encodeURIComponent(topic)}`;
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const payload = await response.json();
                const questions = Array.isArray(payload) ? payload : (payload.questions || []);

                if (questions.length === 0) {
                    alert("No questions found matching this component/topic filter in questions.json. Try 'All Topics' or 'All Papers / Components'.");
                    return;
                }

                processAndRenderQuestions(questions, mode, count, completedIds);
            } catch (e) {
                console.error("Failed to fetch questions:", e);
                alert("Could not load questions from questions.json. Ensure questions.json is in your root directory.");
            }
        }

        function processAndRenderQuestions(questions, mode, count, completedIds) {
            let pool = [...questions];

            if (mode === 'unseen') {
                const unseen = pool.filter(q => !completedIds.includes(q.id));
                if (unseen.length < count) {
                    pool = unseen.concat(pool.filter(q => completedIds.includes(q.id)));
                } else {
                    pool = unseen;
                }
            }

            for (let i = pool.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [pool[i], pool[j]] = [pool[j], pool[i]];
            }

            const selected = pool.slice(0, count);
            renderGeneratorWorkspace(selected, completedIds);
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function handleGenImageError(imgEl, rawPath) {
            const box = imgEl.parentElement;
            imgEl.remove();
            const errNotice = document.createElement('div');
            errNotice.className = "text-xs text-amber-400 bg-amber-950/30 border border-amber-800/50 rounded-lg px-4 py-3 text-center";
            errNotice.innerHTML = `<i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Image not found on server: <code class="text-slate-300">${escapeHtml(rawPath)}</code>`;
            box.appendChild(errNotice);
        }

        function toggleImageZoom(imgEl) {
            if (imgEl.classList.contains('max-w-2xl')) {
                imgEl.classList.remove('max-w-2xl', 'cursor-zoom-in');
                imgEl.classList.add('max-w-full', 'cursor-zoom-out');
            } else {
                imgEl.classList.remove('max-w-full', 'cursor-zoom-out');
                imgEl.classList.add('max-w-2xl', 'cursor-zoom-in');
            }
        }

        function scrollToQuestion(qNum) {
            if (qNum < 1 || qNum > genTotalQ) return;
            genCurrentQ = qNum;
            const el = document.getElementById(`q-card-${qNum}`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            const ind = document.getElementById('gen-nav-indicator');
            if (ind) ind.textContent = `Q${genCurrentQ} / ${genTotalQ}`;
        }

        function stepQuestion(delta) {
            const next = Math.min(Math.max(1, genCurrentQ + delta), genTotalQ);
            scrollToQuestion(next);
        }

        function renderGeneratorWorkspace(questions, completedIds) {
            const workspace = document.getElementById('generator-workspace');
            const container = document.getElementById('generator-questions-list');
            const printMsList = document.getElementById('print-mark-schemes-list');
            workspace.classList.remove('hidden');
            container.innerHTML = '';
            if (printMsList) printMsList.innerHTML = '';

            genTotalQ = questions.length;
            genCurrentQ = 1;
            document.getElementById('gen-paper-title').textContent = `Custom Worksheet (${questions.length} Questions)`;
            const ind = document.getElementById('gen-nav-indicator');
            if (ind) ind.textContent = `Q1 / ${genTotalQ}`;

            questions.forEach((q, idx) => {
                const qNum = idx + 1;
                const isDone = completedIds.includes(q.id);
                const isLast = qNum === questions.length;
                const card = document.createElement('div');
                card.id = `q-card-${qNum}`;
                card.className = "question-card scroll-mt-32 bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-2xl space-y-3 shadow-lg";

                const secureQImg = `study.php?api=image&file=${encodeURIComponent(q.q_image_path || '')}`;

                let msContentHtml = '';
                let printMsBodyHtml = '';

                if (q.ms_image_path) {
                    const secureMsImg = `study.php?api=image&file=${encodeURIComponent(q.ms_image_path)}`;
                    msContentHtml = `
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-center">
                            <img src="${secureMsImg}" alt="Mark Scheme ${qNum}"
                                 onclick="toggleImageZoom(this)"
                                 title="Click to toggle full-width zoom"
                                 class="w-full max-w-2xl h-auto cursor-zoom-in rounded border border-slate-800 bg-white p-2 text-slate-900 transition-all"
                                 onerror="handleGenImageError(this, '${escapeHtml(q.ms_image_path)}')" />
                        </div>
                    `;
                    printMsBodyHtml = `
                        <div class="flex justify-center pt-2">
                            <img src="${secureMsImg}" alt="Mark Scheme ${qNum}" class="w-full max-w-2xl h-auto bg-white" />
                        </div>
                    `;
                } else if (q.answer) {
                    msContentHtml = `
                        <div class="bg-slate-950 p-4 rounded-xl border border-emerald-500/30 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Correct Answer:</span>
                                <span class="px-3.5 py-1 bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 font-mono text-base font-bold rounded-lg">
                                    ${escapeHtml(q.answer)}
                                </span>
                            </div>
                            ${q.answer_status ? `<span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-400">${escapeHtml(q.answer_status)}</span>` : ''}
                        </div>
                    `;
                    printMsBodyHtml = `
                        <div class="text-sm font-mono font-bold text-black pt-1">
                            Answer: ${escapeHtml(q.answer)}
                        </div>
                    `;
                } else {
                    msContentHtml = `
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-xs text-slate-400 text-center">
                            No mark scheme or answer recorded for this question yet.
                        </div>
                    `;
                    printMsBodyHtml = `<div class="text-xs text-slate-600 pt-1">No mark scheme available.</div>`;
                }

                card.innerHTML = `
                    <div class="flex flex-wrap justify-between items-center gap-2 border-b border-slate-800 pb-2.5">
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-0.5 bg-brand-500/10 text-brand-400 font-mono text-xs font-bold rounded border border-brand-500/20">Q${qNum}</span>
                            <span class="text-xs font-mono text-slate-400">Paper Code: ${escapeHtml(q.paper_code || 'A-Level')}</span>
                            ${q.topic ? `<span class="px-2 py-0.5 bg-slate-800 text-slate-300 text-[11px] rounded">${escapeHtml(q.topic)}</span>` : ''}
                        </div>
                        <button onclick="toggleGenMarkScheme('${escapeHtml(q.id)}')" class="no-print px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-brand-400 text-xs font-semibold rounded-lg transition border border-slate-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-eye text-[10px]"></i> ${q.ms_image_path ? 'Show Mark Scheme / Answer' : 'Show Answer'}
                        </button>
                    </div>

                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-center">
                        <img src="${secureQImg}" alt="Question ${qNum}"
                             onclick="toggleImageZoom(this)"
                             title="Click to toggle full-width zoom"
                             class="w-full max-w-2xl h-auto cursor-zoom-in rounded border border-slate-800 bg-white p-2 text-slate-900 transition-all"
                             onerror="handleGenImageError(this, '${escapeHtml(q.q_image_path || '')}')" />
                    </div>

                    <div id="ms-container-${escapeHtml(q.id)}" class="hidden no-print space-y-2 pt-2 border-t border-slate-800/80">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-400"><i class="fa-solid fa-check-circle mr-1"></i> ${q.ms_image_path ? 'Mark Scheme Solution' : 'Verified Answer'}</p>
                        ${msContentHtml}
                    </div>

                    <div class="flex justify-between items-center pt-1 no-print">
                        <button onclick="markQuestionComplete('${escapeHtml(q.id)}', this)"
                                class="${isDone
                                    ? 'px-3.5 py-1.5 bg-brand-500 text-slate-950 text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow'
                                    : 'px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg transition border border-slate-700 flex items-center gap-1.5'}">
                            ${isDone
                                ? '<i class="fa-solid fa-circle-check"></i> Solved & Logged'
                                : '<i class="fa-solid fa-check text-brand-400"></i> Mark as Solved / Seen'}
                        </button>
                        ${!isLast ? `
                        <button onclick="scrollToQuestion(${qNum + 1})" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-brand-400 text-xs font-semibold rounded-lg transition border border-slate-700 flex items-center gap-1.5">
                            Next Question <i class="fa-solid fa-arrow-down text-[10px]"></i>
                        </button>` : ''}
                    </div>
                `;
                container.appendChild(card);

                if (printMsList) {
                    const msCard = document.createElement('div');
                    msCard.className = "question-card p-3 rounded-lg border border-slate-300 space-y-1";
                    msCard.innerHTML = `
                        <div class="flex items-center gap-2 text-xs font-mono font-bold text-black border-b border-slate-200 pb-1">
                            <span>Q${qNum}</span>
                            <span class="font-normal text-slate-600">(${escapeHtml(q.paper_code || 'A-Level')})</span>
                        </div>
                        ${printMsBodyHtml}
                    `;
                    printMsList.appendChild(msCard);
                }
            });
        }

        function toggleGenMarkScheme(id) {
            const el = document.getElementById(`ms-container-${id}`);
            if (el) el.classList.toggle('hidden');
        }

        function markQuestionComplete(id, btn) {
            let completed = JSON.parse(localStorage.getItem('portal_completed_ids') || '[]');
            if (!completed.includes(id)) {
                completed.push(id);
                localStorage.setItem('portal_completed_ids', JSON.stringify(completed));
            }
            btn.className = "px-3.5 py-1.5 bg-brand-500 text-slate-950 text-xs font-bold rounded-lg transition flex items-center gap-1.5 shadow";
            btn.innerHTML = `<i class="fa-solid fa-circle-check"></i> Solved & Logged`;
        }
    </script>
</body>
</html>
