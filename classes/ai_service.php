<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_aiquizgen;

use context_course;

defined('MOODLE_INTERNAL') || die();

/**
 * Service to interface with Moodle core AI manager (\core_ai\manager).
 */
class ai_service {

    /**
     * Builds the prompt instructing the LLM to output valid JSON for university questions.
     *
     * @param string $resourcetext Extracted content from course files.
     * @param string $teacherprompt Additional instructions from teacher.
     * @param int $qcount Number of questions requested.
     * @param string $difficulty Difficulty level.
     * @param array $qtypes Array of question type strings.
     * @param string $lang Language.
     * @param string $coursename Full academic course name.
     * @param string $coursesummary Course summary or syllabus.
     * @return string
     */
    public static function build_system_prompt(
        string $resourcetext,
        string $teacherprompt,
        int $qcount,
        string $difficulty,
        array $qtypes,
        string $lang = 'fa',
        string $coursename = '',
        string $coursesummary = ''
    ): string {
        $typesstr = implode(', ', $qtypes);

        $courseheader = '';
        if (!empty($coursename)) {
            $courseheader .= "ACADEMIC COURSE TITLE / SUBJECT: {$coursename}\n";
        }
        if (!empty($coursesummary)) {
            $courseheader .= "COURSE SYLLABUS & DESCRIPTION:\n{$coursesummary}\n";
        }

        $prompt = <<<PROMPT
You are an expert university professor and exam designer creating an academic examination.
Your mission is to generate exactly {$qcount} high-quality, rigorous academic exam questions for university students.

=== ACADEMIC DOMAIN & COURSE CONTEXT ===
{$courseheader}

=== STRICT PEDAGOGICAL GUARDRAIL & SUBJECT ANCHORING (CRITICAL) ===
1. PRIMARY SUBJECT ANCHOR:
   All generated questions MUST strictly test the academic subject matter of "{$coursename}" and the provided course materials.
2. PEDAGOGICAL INTENT VS. ACADEMIC SUBJECT GUARDRAIL:
   Teachers frequently provide instructions specifying the pedagogical purpose, timing, or format of the exam, such as:
   - "مخصوص پیش‌خوانی" (Pre-reading preparation / baseline diagnostic readiness check)
   - "کوییز کلاسی / آزمک" (Short classroom quiz)
   - "ارزیابی تکوینی / مستمر" (Formative continuous assessment)
   - "آزمون میان‌ترم" (Midterm exam)
   - "سنجش آغازین / ورودی" (Diagnostic readiness check before starting a lecture)
   WARNING: These pedagogical terms define the EXAM FORMAT AND DIFFICULTY LEVEL. They NEVER represent the academic subject matter!
   - Under NO circumstances should you generate questions about "methods of pre-reading", "study skills", "reading comprehension techniques", or "how to prepare for a class"!
   - If the teacher requests "پیش‌خوانی" (pre-reading), you MUST generate foundational, concept-check, or diagnostic readiness questions assessing students' grasp of core concepts, terminology, and definitions of the COURSE SUBJECT ("{$coursename}") before they attend the lecture.
3. SOURCE MATERIAL ADHERENCE:
   Extract questions, formulas, definitions, and real examples directly from the provided course excerpts.
   If excerpts are brief or supplementary, anchor strictly in the academic curriculum of "{$coursename}".

=== EXAM SPECIFICATIONS ===
TARGET AUDIENCE: University Students
DIFFICULTY LEVEL: {$difficulty}
QUESTION TYPES ALLOWED: {$typesstr}
LANGUAGE: {$lang}

=== TEACHER INSTRUCTIONS ===
{$teacherprompt}

=== COURSE MATERIAL EXCERPTS ===
{$resourcetext}

=== OUTPUT INSTRUCTION ===
You MUST output ONLY a valid JSON object matching the following specification, with no markdown code fences, no introductory remarks, and no trailing commentary.

JSON SCHEMA:
{
  "questions": [
    {
      "qtype": "multichoice",
      "name": "Short title",
      "questiontext": "Clear question text in HTML",
      "generalfeedback": "Explanation of correct answer",
      "defaultmark": 1.0,
      "answers": [
        {"text": "Option 1", "iscorrect": true, "feedback": "Why it is correct"},
        {"text": "Option 2", "iscorrect": false, "feedback": "Why it is wrong"},
        {"text": "Option 3", "iscorrect": false, "feedback": "Why it is wrong"},
        {"text": "Option 4", "iscorrect": false, "feedback": "Why it is wrong"}
      ]
    },
    {
      "qtype": "truefalse",
      "name": "Short title",
      "questiontext": "Statement in HTML",
      "correctanswer": true,
      "feedbacktrue": "Explanation if true",
      "feedbackfalse": "Explanation if false",
      "defaultmark": 1.0
    },
    {
      "qtype": "shortanswer",
      "name": "Short title",
      "questiontext": "Question requiring short term",
      "acceptedanswers": ["Term1", "Alternative"],
      "defaultmark": 1.0
    },
    {
      "qtype": "essay",
      "name": "Short title",
      "questiontext": "Analytical essay prompt",
      "rubric": "Grading criteria for the teacher",
      "defaultmark": 3.0
    },
    {
      "qtype": "match",
      "name": "Short title",
      "questiontext": "Match the following items",
      "pairs": [
        {"question": "Item 1", "answer": "Match 1"},
        {"question": "Item 2", "answer": "Match 2"}
      ],
      "defaultmark": 2.0
    },
    {
      "qtype": "ordering",
      "name": "Short title",
      "questiontext": "Order the following steps correctly",
      "items": [
        "First step in correct order",
        "Second step in correct order",
        "Third step in correct order",
        "Fourth step in correct order"
      ],
      "defaultmark": 2.0
    }
  ]
}
PROMPT;

        return $prompt;
    }

    /**
     * Executes the AI generation action through Moodle core AI manager.
     *
     * @param int $contextid
     * @param int $userid
     * @param string $prompt
     * @return array Decoded questions array.
     */
    public static function call_moodle_ai(int $contextid, int $userid, string $prompt): array {
        $action = new \core_ai\aiactions\generate_text(
            contextid: $contextid,
            userid: $userid,
            prompttext: $prompt,
        );

        $manager = \core\di::get(\core_ai\manager::class);
        $response = $manager->process_action($action);

        if (!$response->get_success()) {
            $errmsg = method_exists($response, 'get_errormessage') ? $response->get_errormessage() : ($response->get_error() ?? 'Unknown AI error');
            throw new \moodle_exception('ai_generation_failed', 'local_aiquizgen', '', $errmsg);
        }

        $rawtext = $response->get_response_data()['generatedcontent'] ?? '';

        // Strip reasoning tags if present (e.g., DeepSeek <think>...</think>).
        if (class_exists('\core_ai\helper')) {
            $rawtext = \core_ai\helper::strip_reasoning_tags($rawtext);
        }

        // Clean any accidental markdown backticks.
        $rawtext = preg_replace('/^```(?:json)?\s*/i', '', trim($rawtext));
        $rawtext = preg_replace('/\s*```$/', '', $rawtext);

        $decoded = json_decode($rawtext, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded['questions'])) {
            // Attempt extraction of JSON substring if surrounded by prose.
            if (preg_match('/\{[\s\S]*"questions"[\s\S]*\}/', $rawtext, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (empty($decoded['questions'])) {
            throw new \moodle_exception('json_parse_error', 'local_aiquizgen');
        }

        return $decoded['questions'];
    }

    /**
     * Dispatches generation to AvalAI, Mastra Agent Workflow, or Moodle core_ai.
     *
     * @param int $contextid
     * @param int $userid
     * @param array $payload
     * @param string $systemprompt
     * @return array Decoded questions array.
     */
    public static function execute_generation(int $contextid, int $userid, array $payload, string $systemprompt): array {
        $engine = get_config('local_aiquizgen', 'ai_engine');

        // 1. Direct AvalAI API with DeepSeek / Flash models.
        if ($engine === 'avalai' || empty($engine)) {
            try {
                return self::call_avalai_api($systemprompt);
            } catch (\Throwable $e) {
                // If Mastra is configured, try fallback, otherwise rethrow the real AvalAI error.
                $endpoint = get_config('local_aiquizgen', 'mastra_endpoint');
                if (empty($endpoint)) {
                    throw $e;
                }
                debugging('AvalAI API error, attempting fallback to Mastra: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // 2. Mastra Agent Workflow.
        $endpoint = get_config('local_aiquizgen', 'mastra_endpoint');
        if ($engine === 'mastra_hybrid' && !empty($endpoint)) {
            try {
                $questions = self::call_mastra_workflow($endpoint, $payload);
                if (!empty($questions)) {
                    return $questions;
                }
            } catch (\Throwable $e) {
                debugging('Mastra workflow unreachable, falling back to Moodle core_ai: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // 3. Fallback to Moodle core_ai.
        return self::call_moodle_ai($contextid, $userid, $systemprompt);
    }

    /**
     * Executes generation via AvalAI API (OpenAI-compatible).
     *
     * @param string $systemprompt
     * @param string $model
     * @return array
     */
    public static function call_avalai_api(string $systemprompt, string $model = ''): array {
        $apikey = get_config('local_aiquizgen', 'avalai_api_key') ?: 'aa-6ZlvSSRsw9smYSEZFhqp90LzwmP2qDVSzVAlV8cqtdV24wpE';
        $endpoint = get_config('local_aiquizgen', 'avalai_endpoint') ?: 'https://api.avalai.org/v1';
        $model = !empty($model) ? $model : (get_config('local_aiquizgen', 'avalai_model') ?: 'deepseek-v4.1-flash');

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $systemprompt,
                ],
            ],
            'temperature' => 0.4,
            'max_tokens' => 4096,
        ];

        $attempts = 0;
        $maxattempts = 2;
        $response = null;
        $httpcode = 0;
        $curlerror = '';

        while ($attempts < $maxattempts) {
            $attempts++;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, rtrim($endpoint, '/') . '/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlerror = curl_error($ch);
            curl_close($ch);

            if ($httpcode === 200 && !empty($response)) {
                break;
            }
            if ($attempts < $maxattempts && ($httpcode === 429 || $httpcode >= 500)) {
                sleep(2);
                continue;
            }
        }

        if ($httpcode !== 200 || empty($response)) {
            throw new \moodle_exception('avalai_api_error', 'local_aiquizgen', '', "HTTP $httpcode: $curlerror $response");
        }
        $curlerror = curl_error($ch);
        curl_close($ch);

        if ($httpcode !== 200 || empty($response)) {
            throw new \moodle_exception('avalai_api_error', 'local_aiquizgen', '', "HTTP $httpcode: $curlerror $response");
        }

        $result = json_decode($response, true);
        $rawtext = $result['choices'][0]['message']['content'] ?? '';

        // Strip reasoning tags if present (e.g., DeepSeek <think>...</think>).
        if (class_exists('\core_ai\helper')) {
            $rawtext = \core_ai\helper::strip_reasoning_tags($rawtext);
        } else {
            $rawtext = preg_replace('/<think>[\s\S]*?<\/think>/', '', $rawtext);
        }

        // Clean markdown backticks.
        $rawtext = preg_replace('/^```(?:json)?\s*/i', '', trim($rawtext));
        $rawtext = preg_replace('/\s*```$/', '', $rawtext);

        $decoded = json_decode($rawtext, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded['questions'])) {
            if (preg_match('/\{[\s\S]*"questions"[\s\S]*\}/', $rawtext, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (empty($decoded['questions'])) {
            throw new \moodle_exception('json_parse_error', 'local_aiquizgen', '', substr($rawtext, 0, 500));
        }

        return $decoded['questions'];
    }

    /**
     * Executes the Mastra Agent Workflow via HTTP POST.
     *
     * @param string $endpoint
     * @param array $payload
     * @return array
     */
    public static function call_mastra_workflow(string $endpoint, array $payload): array {
        $client = new \core\http_client();
        $response = $client->post($endpoint . '/start', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'inputData' => $payload,
            ],
            'timeout' => 90,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \moodle_exception('mastra_http_error', 'local_aiquizgen', '', 'HTTP ' . $response->getStatusCode());
        }

        $body = (string) $response->getBody();
        $result = json_decode($body, true);

        // Mastra suspend payload returns { status: 'suspended', suspendPayload: { candidates: [...] } }
        if (!empty($result['suspendPayload']['candidates'])) {
            return $result['suspendPayload']['candidates'];
        }

        if (!empty($result['results']['candidates'])) {
            return $result['results']['candidates'];
        }

        if (!empty($result['finalQuestions'])) {
            return $result['finalQuestions'];
        }

        return [];
    }
}
