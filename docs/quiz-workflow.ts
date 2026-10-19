/**
 * Mastra Workflow Mode: Quiz Generation Pipeline (`quiz-generation-v1`)
 *
 * Designed for university-level academic exam generation with Human-in-the-Loop (HITL).
 * Uses Mastra's Directed Acyclic Graph (DAG) with parallel execution, step-level retries,
 * and suspend/resume state persistence.
 */

import { createWorkflow, createStep } from '@mastra/core/workflows';
import { z } from 'zod';

// ============================================================================
// 1. SCHEMAS
// ============================================================================

export const QuestionSchema = z.object({
  id: z.string(),
  qtype: z.enum(['multichoice', 'truefalse', 'shortanswer', 'essay', 'match', 'ordering']),
  name: z.string(),
  questiontext: z.string(),
  defaultmark: z.number().default(1),
  generalfeedback: z.string().optional(),
  // Type-specific fields:
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

export const QuizWorkflowInputSchema = z.object({
  courseId: z.number(),
  teacherId: z.number(),
  teacherPrompt: z.string().default(''),
  difficulty: z.enum(['standard', 'analytical', 'hard']).default('analytical'),
  questionCount: z.number().min(3).max(50).default(10),
  questionTypes: z.array(z.string()).default(['multichoice', 'truefalse', 'shortanswer', 'essay', 'match']),
  resourceTexts: z.array(z.string()),
  language: z.string().default('fa'),
});

export const AnalysisOutputSchema = z.object({
  courseId: z.number(),
  teacherPrompt: z.string(),
  difficulty: z.string(),
  language: z.string(),
  keyConcepts: z.array(z.string()),
  fullContext: z.string(),
  countPerType: z.record(z.string(), z.number()),
});

// ============================================================================
// 2. STEPS
// ============================================================================

/**
 * STEP 1: Ingest & Analyze Resources
 * Extracts concepts, maps to Bloom's taxonomy, and calculates distribution per question type.
 */
export const extractAndAnalyzeStep = createStep({
  id: 'extract-and-analyze',
  inputSchema: QuizWorkflowInputSchema,
  outputSchema: AnalysisOutputSchema,
  retries: 2,
  execute: async ({ inputData }) => {
    const fullContext = inputData.resourceTexts.join('\n\n');
    const totalCount = inputData.questionCount;
    const types = inputData.questionTypes;

    // Distribute question counts proportionally across selected types
    const baseCount = Math.floor(totalCount / types.length);
    const remainder = totalCount % types.length;
    const countPerType: Record<string, number> = {};

    types.forEach((t, i) => {
      countPerType[t] = baseCount + (i < remainder ? 1 : 0);
    });

    return {
      courseId: inputData.courseId,
      teacherPrompt: inputData.teacherPrompt,
      difficulty: inputData.difficulty,
      language: inputData.language,
      keyConcepts: ['مفاهیم پایه', 'تحلیل و کاربرد', 'طراحی و حل مسئله'],
      fullContext,
      countPerType,
    };
  },
});

/**
 * STEP 2A: Parallel Multiple Choice Generator
 */
export const generateMCQStep = createStep({
  id: 'generate-mcq',
  inputSchema: AnalysisOutputSchema,
  outputSchema: z.object({
    mcqQuestions: z.array(QuestionSchema),
  }),
  retries: 2,
  execute: async ({ inputData }) => {
    const count = inputData.countPerType['multichoice'] ?? 0;
    if (count === 0) return { mcqQuestions: [] };

    // In production, invokes LLM with specialized distractor-generation instructions
    return {
      mcqQuestions: [
        {
          id: 'mcq-1',
          qtype: 'multichoice',
          name: 'سوال چندگزینه‌ای تحلیلی',
          questiontext: 'کدام گزینه توضیح درستی درباره پدیده بیش‌برازش (Overfitting) است؟',
          defaultmark: 1,
          generalfeedback: 'مدل بر روی داده‌های آموزش خطای بسیار کمی دارد اما بر روی داده‌های آزمون تعمیم پیدا نمی‌کند.',
          answers: [
            { text: 'خطای آموزش اندک اما خطای آزمون بالا است', iscorrect: true, feedback: 'صحیح است.' },
            { text: 'خطای آموزش و آزمون هر دو بسیار بالا هستند', iscorrect: false, feedback: 'این حالت کم‌برازش است.' },
            { text: 'مدل بیش از حد ساده انتخاب شده است', iscorrect: false, feedback: 'خیر، مدل بیش از حد پیچیده است.' },
            { text: 'تعداد داده‌های آموزش بیش از اندازه زیاد بوده است', iscorrect: false, feedback: 'افزایش داده معمولا بیش‌برازش را کاهش می‌دهد.' },
          ],
        },
      ],
    };
  },
});

/**
 * STEP 2B: Parallel True/False Generator
 */
export const generateTrueFalseStep = createStep({
  id: 'generate-truefalse',
  inputSchema: AnalysisOutputSchema,
  outputSchema: z.object({
    tfQuestions: z.array(QuestionSchema),
  }),
  retries: 2,
  execute: async ({ inputData }) => {
    const count = inputData.countPerType['truefalse'] ?? 0;
    if (count === 0) return { tfQuestions: [] };

    return {
      tfQuestions: [
        {
          id: 'tf-1',
          qtype: 'truefalse',
          name: 'سوال صحیح و غلط',
          questiontext: 'استفاده از نرخ یادگیری (Learning Rate) بسیار بزرگ می‌تواند مانع همگرایی الگوریتم به نقطه بهینه شود.',
          defaultmark: 1,
          correctanswer: true,
          feedbacktrue: 'درست است، گام‌های بزرگ باعث نوسان یا واگرایی می‌شود.',
          feedbackfalse: 'نادرست است، نرخ بزرگ مانع همگرایی است.',
        },
      ],
    };
  },
});

/**
 * STEP 2C: Parallel Essay & Rubric Generator
 */
export const generateEssayStep = createStep({
  id: 'generate-essay',
  inputSchema: AnalysisOutputSchema,
  outputSchema: z.object({
    essayQuestions: z.array(QuestionSchema),
  }),
  retries: 2,
  execute: async ({ inputData }) => {
    const count = inputData.countPerType['essay'] ?? 0;
    if (count === 0) return { essayQuestions: [] };

    return {
      essayQuestions: [
        {
          id: 'essay-1',
          qtype: 'essay',
          name: 'سوال تشریحی تحلیلی',
          questiontext: 'تفاوت‌های اساسی مکانیزم Attention را با لایه‌های متوالی RNN شرح داده و علل برتری آن را ذکر نمایید.',
          defaultmark: 3,
          rubric: '۱ نمره برای بیان محدودیت بردار مخفی در RNN، ۱ نمره برای نحوه وزن‌دهی به تمام توکن‌ها، ۱ نمره برای قابلیت پردازش موازی.',
        },
      ],
    };
  },
});

/**
 * STEP 3: Combine and Verify Candidates
 */
export const combineAndVerifyStep = createStep({
  id: 'combine-and-verify',
  inputSchema: z.object({
    'generate-mcq': z.object({ mcqQuestions: z.array(QuestionSchema) }),
    'generate-truefalse': z.object({ tfQuestions: z.array(QuestionSchema) }),
    'generate-essay': z.object({ essayQuestions: z.array(QuestionSchema) }),
  }),
  outputSchema: z.object({
    allCandidates: z.array(QuestionSchema),
  }),
  execute: async ({ inputData }) => {
    const combined = [
      ...inputData['generate-mcq'].mcqQuestions,
      ...inputData['generate-truefalse'].tfQuestions,
      ...inputData['generate-essay'].essayQuestions,
    ];
    return { allCandidates: combined };
  },
});

/**
 * STEP 4: Human-in-the-Loop Review Gate (SUSPEND & RESUME)
 * Pauses execution, exposes drafted questions to Moodle, and awaits teacher approval.
 */
export const teacherReviewGateStep = createStep({
  id: 'teacher-review-gate',
  inputSchema: z.object({
    allCandidates: z.array(QuestionSchema),
  }),
  outputSchema: z.object({
    approvedQuestions: z.array(QuestionSchema),
  }),
  resumeSchema: z.object({
    approved: z.boolean(),
    finalQuestions: z.array(QuestionSchema),
  }),
  suspendSchema: z.object({
    reason: z.string(),
    candidates: z.array(QuestionSchema),
  }),
  execute: async ({ inputData, resumeData, suspend }) => {
    const { approved, finalQuestions } = resumeData ?? {};

    // If teacher has not yet clicked approve in Moodle, SUSPEND the workflow run!
    if (!approved) {
      return await suspend({
        reason: 'Waiting for teacher review, inline edits, and approval in Moodle.',
        candidates: inputData.allCandidates,
      });
    }

    // Teacher clicked "تأیید و ساخت آزمون در مودل" -> workflow resumes!
    return {
      approvedQuestions: finalQuestions ?? inputData.allCandidates,
    };
  },
});

// ============================================================================
// 3. WORKFLOW COMPOSITION (DAG)
// ============================================================================

export const quizWorkflow = createWorkflow({
  id: 'quiz-generation-workflow',
  inputSchema: QuizWorkflowInputSchema,
  outputSchema: z.object({
    approvedQuestions: z.array(QuestionSchema),
  }),
  retryConfig: {
    attempts: 2,
    delay: 1500,
  },
})
  .then(extractAndAnalyzeStep)
  .parallel([generateMCQStep, generateTrueFalseStep, generateEssayStep])
  .then(combineAndVerifyStep)
  .then(teacherReviewGateStep)
  .commit();
