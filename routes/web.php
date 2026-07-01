<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, \App\Http\Middleware\SetLocale::SUPPORTED_LOCALES, true)) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

use App\Http\Controllers\Admin\LiveControlController;
use App\Http\Controllers\Admin\LiveSessionController;
use App\Http\Controllers\Admin\SurveyController;
use App\Http\Controllers\Admin\SurveyResultController;
use App\Http\Controllers\LivePollController;
use App\Http\Controllers\SurveyWizardController;

Route::get('/s/{survey}', [SurveyWizardController::class, 'show'])->name('surveys.wizard');
Route::post('/s/{survey}/submit', [SurveyWizardController::class, 'submit'])->name('surveys.submit');

// Live polling (public voter screen).
Route::get('/live/{liveSession}', [LivePollController::class, 'show'])->name('live.show');
Route::get('/live/{liveSession}/state', [LivePollController::class, 'state'])->name('live.state');
Route::post('/live/{liveSession}/identify', [LivePollController::class, 'identify'])->name('live.identify');
Route::post('/live/{liveSession}/vote', [LivePollController::class, 'vote'])->name('live.vote');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::resource('surveys', SurveyController::class);
    Route::get('surveys/{survey}/results', [SurveyResultController::class, 'index'])->name('surveys.results');

    Route::post('surveys/{survey}/responses/{response}/toggle-highlight', function() {
        return response()->json(['hello' => 'world']);
    })->name('surveys.responses.toggle-highlight');

    // Live polling admin (CRUD + control panel).
    Route::resource('live-sessions', LiveSessionController::class)->except(['show']);
    Route::get('live-sessions/{liveSession}/control', [LiveControlController::class, 'panel'])->name('live-sessions.control');
    Route::get('live-sessions/{liveSession}/responses', [LiveControlController::class, 'responses'])->name('live-sessions.responses');
    Route::post('live-sessions/{liveSession}/participants/{participant}/toggle-master', [LiveControlController::class, 'toggleMaster'])->name('live-sessions.participants.toggle-master')->scopeBindings();
    Route::post('live-sessions/{liveSession}/start', [LiveControlController::class, 'start'])->name('live-sessions.start');
    Route::post('live-sessions/{liveSession}/finish', [LiveControlController::class, 'finish'])->name('live-sessions.finish');
    Route::post('live-sessions/{liveSession}/questions', [LiveControlController::class, 'askQuestion'])->name('live-sessions.questions.ask');
    Route::patch('live-sessions/{liveSession}/questions/{question}', [LiveControlController::class, 'updateQuestion'])->name('live-sessions.questions.update')->scopeBindings();
    Route::post('live-sessions/{liveSession}/questions/{question}/open', [LiveControlController::class, 'openQuestion'])->name('live-sessions.questions.open')->scopeBindings();
    Route::post('live-sessions/{liveSession}/questions/{question}/close', [LiveControlController::class, 'closeQuestion'])->name('live-sessions.questions.close')->scopeBindings();
});

Route::get('test-toggle/{survey}/{response}', [SurveyResultController::class, 'toggleHighlight']);
