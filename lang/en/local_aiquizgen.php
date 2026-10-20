<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Quiz Generator';
$string['aiquizgen:view'] = 'View AI Quiz Generator';
$string['aiquizgen:generate'] = 'Generate quizzes using AI';
$string['aiquizgen:manage'] = 'Manage AI Quiz Generator settings';
$string['settings_general'] = 'General Configuration';
$string['settings_general_desc'] = 'Configure global settings for AI-powered quiz generation.';
$string['enablecoursetab'] = 'Enable Course Navigation Tab';
$string['enablecoursetab_desc'] = 'When enabled, adds an "AI Quiz Generator" tab to the secondary navigation of courses.';
$string['maxquestions'] = 'Maximum Questions per Request';
$string['maxquestions_desc'] = 'The maximum number of questions a teacher can generate in a single session.';
$string['settings_avalai'] = 'AvalAI Service (Direct Access)';
$string['settings_avalai_desc'] = 'Connect directly to AvalAI for ultra-fast generation via DeepSeek V4 Flash, Gemini Flash, or OpenAI models.';
$string['engine_avalai'] = 'AvalAI Direct Service (Recommended: DeepSeek V4.1 Flash)';
$string['avalai_api_key'] = 'AvalAI API Key';
$string['avalai_api_key_desc'] = 'Valid API key from avalai.ir or avalai.org.';
$string['avalai_model'] = 'AvalAI Model ID';
$string['avalai_model_desc'] = 'Exact model ID on AvalAI (e.g. deepseek-v4.1-flash).';
$string['avalai_endpoint'] = 'AvalAI API Endpoint';
$string['avalai_endpoint_desc'] = 'Base URL for API requests (default: https://api.avalai.org/v1).';
$string['avalai_api_error'] = 'AvalAI API communication failed: {$a}';
$string['settings_mastra'] = 'Mastra AI Workflow Orchestrator';
$string['settings_mastra_desc'] = 'Configuration for connecting to the Mastra TypeScript Agentic Workflow engine for durable, multi-step generation with Human-in-the-Loop.';
$string['ai_engine'] = 'AI Execution Engine';
$string['ai_engine_desc'] = 'Choose AI engine: Mastra Hybrid (with automatic Moodle AI fallback), or native Moodle core_ai only.';
$string['engine_mastra_hybrid'] = 'Mastra Agent Workflow (with automatic Moodle AI fallback)';
$string['engine_moodle_ai'] = 'Moodle native core_ai only';
$string['mastra_endpoint'] = 'Mastra Workflow Endpoint URL';
$string['mastra_endpoint_desc'] = 'Full URL to the Mastra quiz workflow (e.g. http://localhost:3000/api/workflows/quiz-generation-workflow)';
$string['default_category_name'] = 'AI Generated Questions';
$string['default_quiz_intro'] = 'This quiz was generated with AI assistance and approved by the course instructor.';
$string['publish_success_message'] = 'The questions were successfully added to the Question Bank and the Quiz activity is live in your course.';
$string['ai_generation_failed'] = 'AI generation request failed: {$a}';
$string['json_parse_error'] = 'Could not parse the AI response as structured JSON. Please retry.';
$string['invalid_questions_payload'] = 'Invalid questions payload provided.';
