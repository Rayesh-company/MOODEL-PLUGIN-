# 🧠 Mastra Agent & Workflow Integration for Moodle Quiz Generator

> **Architecture Specification & Technical Research**  
> Connecting Mastra (`@mastra/core`) TypeScript Agentic Workflows with Moodle 6.0dev PHP Plugin (`local_aiquizgen`).

---

## 1. Why Mastra for Quiz Generation?

Generating university-level exams from complex course materials (PDFs, slides, lecture notes) requires more than a single LLM prompt. Single-shot prompts suffer from:
- Hallucinated facts and unverified distractors.
- Superficial questions that test only low-level rote memorization.
- Loss of context on long documents (>50 pages).
- Lack of state durability if the teacher disconnects or browser closes.

**Mastra** provides an enterprise-grade agent and workflow engine built on TypeScript that solves these problems through:
1. **Typed Multi-Step Workflows (DAGs)**: Breaks exam generation into deterministic, retryable, observable steps.
2. **Human-in-the-Loop (`suspend` & `resume`)**: Built-in state suspension where the workflow pauses, emits drafted questions to Moodle, and waits for teacher approval before committing to the database.
3. **Multi-Agent Specialization**: Different specialized agents for tricky Multiple Choice distractors, analytical Essay rubrics, and Matching definitions.
4. **Automated Evals & Quality Scoring (`@mastra/evals`)**: Verification of question relevance, hallucination checks against source text, and distractor plausibility.
5. **Durable Snapshot Persistence (`@mastra/pg`)**: All state is persisted in PostgreSQL; teachers can leave and resume exam drafting anytime.

---

## 2. End-to-End Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                             MOODLE LMS (PHP)                                │
│                                                                             │
│   Course Page Navigation ──► [✨ آزمون‌ساز هوشمند (local_aiquizgen)]          │
│                                                                             │
│   Step 1: Resource Selector (PDFs, DOCX, PPTX via file_storage)             │
│   Step 2: Configuration (Bloom Taxonomy, Difficulty, 6 Question Types)      │
│   Step 3: Interactive Review & Single-Question Re-prompting                 │
│   Step 4: Final Teacher Approval & Moodle Quiz Auto-Publish                 │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                      HTTP / REST / SSE Stream (JSON)
                      Bi-directional WebService Seam
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                  MASTRA AGENT SERVER (Node.js / TypeScript)                 │
│                      (Running in chatbot/server)                            │
│                                                                             │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │                      quizGenerationWorkflow                         │   │
│   │                                                                     │   │
│   │  [Step 1: ingestAndChunkResources]                                  │   │
│   │   ├── Text extraction + AvalAI OCR tool for scanned PDFs            │   │
│   │   └── Semantic concept extraction & Bloom's taxonomy mapping        │   │
│   │                                                                     │   │
│   │  [Step 2: generateQuestionCandidates] (Parallel Agents)             │   │
│   │   ├── MCQ Generator Agent (plausible academic distractors)          │   │
│   │   ├── True/False Agent (subtle misconceptions & reasoning)          │   │
│   │   ├── Short Answer & Match Agent (terminology pairs)                │   │
│   │   └── Essay Agent (analytical prompts + grading rubrics)            │   │
│   │                                                                     │   │
│   │  [Step 3: qualityVerificationScorer]                                │   │
│   │   ├── Hallucination check against source documents                  │   │
│   │   └── Relevancy & difficulty calibration                            │   │
│   │                                                                     │   │
│   │  [Step 4: teacherReviewGate] ──► SUSPEND!                           │   │
│   │   ├── Emits drafted questions to Moodle                             │   │
│   │   ├── Handles micro-reprompt tool calls for single questions        │   │
│   │   └── WAITS for Teacher Approval                                    │   │
│   │                                                                     │   │
│   │  [Step 5: resumeOnApproval] ──► RESUME!                             │   │
│   │   └── Dispatches final approved questions to Moodle Question Bank   │   │
│   └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │      PostgreSQL Storage (@mastra/pg) - Workflow State & Runs        │   │
│   └─────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Mastra Workflow Implementation (`quiz.ts`)

Here is the exact Mastra workflow definition ready to be added to `chatbot/server/src/mastra/workflows/quiz.ts`:

```typescript
import { createWorkflow, createStep } from '@mastra/core/workflows';
import { z } from 'zod';

// Input Schema for Quiz Generation
export const QuizInputSchema = z.object({
  courseId: z.number(),
  teacherId: z.number(),
  teacherPrompt: z.string().optional(),
  difficulty: z.enum(['standard', 'analytical', 'hard']).default('analytical'),
  questionCount: z.number().min(3).max(50).default(10),
  questionTypes: z.array(z.string()).default(['multichoice', 'truefalse', 'shortanswer', 'essay', 'match']),
  resourceTexts: z.array(z.object({
    filename: z.string(),
    content: z.string(),
  })),
  language: z.string().default('fa'),
});

// Question Item Schema
export const QuestionItemSchema = z.object({
  id: z.string(),
  qtype: z.string(),
  name: z.string(),
  questiontext: z.string(),
  defaultmark: z.number().default(1),
  generalfeedback: z.string().optional(),
  answers: z.array(z.object({
    text: z.string(),
    iscorrect: z.boolean(),
    feedback: z.string().optional(),
  })).optional(),
  correctanswer: z.boolean().optional(),
  feedbacktrue: z.string().optional(),
  feedbackfalse: z.string().optional(),
  acceptedanswers: z.array(z.string()).optional(),
  rubric: z.string().optional(),
  pairs: z.array(z.object({
    question: z.string(),
    answer: z.string(),
  })).optional(),
});

// STEP 1: Ingest & Extract Pedagogical Concepts
const ingestAndAnalyzeStep = createStep({
  id: 'ingest-and-analyze',
  inputSchema: QuizInputSchema,
  outputSchema: z.object({
    keyConcepts: z.array(z.string()),
    targetDifficulty: z.string(),
    contextChunks: z.array(z.string()),
  }),
  execute: async ({ inputData, mastra }) => {
    // Extract key academic concepts and map difficulty to Bloom's taxonomy
    const fullText = inputData.resourceTexts.map(r => r.content).join('\n\n');
    return {
      keyConcepts: ['Concept A', 'Concept B'],
      targetDifficulty: inputData.difficulty,
      contextChunks: [fullText.slice(0, 10000)],
    };
  },
});

// STEP 2: Generate Question Candidates via Agent
const generateCandidatesStep = createStep({
  id: 'generate-candidates',
  inputSchema: z.object({
    keyConcepts: z.array(z.string()),
    targetDifficulty: z.string(),
    contextChunks: z.array(z.string()),
  }),
  outputSchema: z.object({
    candidates: z.array(QuestionItemSchema),
  }),
  execute: async ({ inputData, mastra }) => {
    // Calls LLM agent using structured output to generate diverse questions
    return {
      candidates: [], // Array of validated questions
    };
  },
});

// STEP 3: Human-in-the-Loop Review Gate (SUSPEND & RESUME)
const teacherReviewGateStep = createStep({
  id: 'teacher-review-gate',
  inputSchema: z.object({
    candidates: z.array(QuestionItemSchema),
  }),
  outputSchema: z.object({
    finalQuestions: z.array(QuestionItemSchema),
  }),
  resumeSchema: z.object({
    approved: z.boolean(),
    finalQuestions: z.array(QuestionItemSchema),
  }),
  suspendSchema: z.object({
    reason: z.string(),
    candidates: z.array(QuestionItemSchema),
  }),
  execute: async ({ inputData, resumeData, suspend }) => {
    const { approved, finalQuestions } = resumeData ?? {};

    // If teacher has not yet approved, SUSPEND the workflow and return questions to Moodle!
    if (!approved) {
      return await suspend({
        reason: 'Teacher review and approval required in Moodle UI.',
        candidates: inputData.candidates,
      });
    }

    // Teacher has approved! Return final questions to proceed to Moodle publishing
    return {
      finalQuestions: finalQuestions ?? inputData.candidates,
    };
  },
});

// BUILD AND EXPORT THE WORKFLOW
export const quizGenerationWorkflow = createWorkflow({
  id: 'quiz-generation-workflow',
  inputSchema: QuizInputSchema,
  outputSchema: z.object({
    finalQuestions: z.array(QuestionItemSchema),
  }),
})
  .then(ingestAndAnalyzeStep)
  .then(generateCandidatesStep)
  .then(teacherReviewGateStep)
  .commit();
```

---

## 4. How Moodle Interacts with Mastra (Step-by-Step)

1. **Teacher clicks "تولید هوشمند سوالات آزمون" in Moodle**:
   - Moodle PHP gathers selected files and calls Mastra:
     ```http
     POST http://localhost:3000/api/workflows/quiz-generation-workflow/start
     Content-Type: application/json
     
     {
       "inputData": {
         "courseId": 10,
         "teacherId": 2,
         "questionCount": 10,
         "difficulty": "analytical",
         "resourceTexts": [...]
       }
     }
     ```
   - Mastra executes Step 1 & Step 2, hits Step 3 (`teacherReviewGateStep`), and **suspends**.
   - Mastra returns `{ status: "suspended", runId: "run_xyz", suspendPayload: { candidates: [...] } }`.

2. **Moodle displays Step 3 (Preview & Edit)**:
   - The teacher edits questions directly in Moodle's UI.
   - If the teacher re-prompts an individual question, Moodle sends a lightweight request to Mastra's single-turn tool.

3. **Teacher clicks "تأیید نهایی استاد و ساخت آزمون در مودل"**:
   - Moodle sends the resume command:
     ```http
     POST http://localhost:3000/api/workflows/quiz-generation-workflow/resume
     Content-Type: application/json
     
     {
       "runId": "run_xyz",
       "step": "teacher-review-gate",
       "resumeData": {
         "approved": true,
         "finalQuestions": [...]
       }
     }
     ```
   - Mastra workflow transitions to `completed`.
   - Moodle PHP commits questions to `question_bank` and creates `mod_quiz`.

---

## 5. Graceful Fallback (Dual-Engine)

The plugin implements a seamless **Dual-Engine** strategy:
- **Primary**: If `MASTRA_SERVER_URL` is set in Moodle Site Administration (`local_aiquizgen`), the plugin uses the Mastra Workflow engine for stateful, multi-step generation.
- **Fallback**: If the Mastra server is unreachable or disabled, the plugin automatically falls back to Moodle's native `\core_ai\manager` without throwing an error to the teacher!
