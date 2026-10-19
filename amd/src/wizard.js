// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    let courseId = 0;
    let questionsState = [];

    const Wizard = {
        init: function(id) {
            courseId = id;
            this.bindEvents();
            this.loadCourseFiles();
        },

        bindEvents: function() {
            const self = this;

            // Main view tabs (Generator Wizard vs Analytics Dashboard)
            const tabGen = document.getElementById('tab-generator-btn');
            const tabAnalytics = document.getElementById('tab-analytics-btn');
            const viewGen = document.getElementById('view-generator-pane');
            const viewAnalytics = document.getElementById('view-analytics-pane');

            if (tabGen && tabAnalytics && viewGen && viewAnalytics) {
                tabGen.addEventListener('click', function() {
                    tabGen.classList.add('active');
                    tabAnalytics.classList.remove('active');
                    viewGen.classList.remove('d-none');
                    viewAnalytics.classList.add('d-none');
                });

                tabAnalytics.addEventListener('click', function() {
                    tabAnalytics.classList.add('active');
                    tabGen.classList.remove('active');
                    viewAnalytics.classList.remove('d-none');
                    viewGen.classList.add('d-none');
                });
            }

            // Nav tabs
            document.querySelectorAll('.step-nav-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const step = parseInt(this.getAttribute('data-step'), 10);
                    self.showStep(step);
                });
            });

            // Step 1 to 2
            const toStep2 = document.getElementById('btn-to-step-2');
            if (toStep2) toStep2.addEventListener('click', () => self.showStep(2));

            // Step 2 back to 1
            const backTo1 = document.getElementById('btn-back-to-step-1');
            if (backTo1) backTo1.addEventListener('click', () => self.showStep(1));

            // Slider update
            const slider = document.getElementById('input-qcount');
            if (slider) {
                slider.addEventListener('input', function() {
                    const lbl = document.getElementById('label-qcount');
                    if (lbl) lbl.textContent = `${this.value} سوال`;
                });
            }

            // Step 2 Generate
            const genBtn = document.getElementById('btn-generate-ai');
            if (genBtn) genBtn.addEventListener('click', () => self.generateQuestions());

            // Step 3 to 4
            const toStep4 = document.getElementById('btn-to-step-4');
            if (toStep4) toStep4.addEventListener('click', () => self.showStep(4));

            // Step 3 back to 2
            const backTo2 = document.getElementById('btn-back-to-step-2');
            if (backTo2) backTo2.addEventListener('click', () => self.showStep(2));

            // Step 4 back to 3
            const backTo3 = document.getElementById('btn-back-to-step-3');
            if (backTo3) backTo3.addEventListener('click', () => self.showStep(3));

            // Step 4 Publish
            const pubBtn = document.getElementById('btn-publish-final');
            if (pubBtn) pubBtn.addEventListener('click', () => self.publishQuiz());

            // Refresh files
            const refreshFiles = document.getElementById('btn-refresh-files');
            if (refreshFiles) refreshFiles.addEventListener('click', () => self.loadCourseFiles());
        },

        showStep: function(step) {
            for (let i = 1; i <= 4; i++) {
                const pane = document.getElementById(`pane-step-${i}`);
                const btn = document.querySelector(`.step-nav-btn[data-step="${i}"]`);
                if (pane) {
                    if (i === step) pane.classList.remove('d-none');
                    else pane.classList.add('d-none');
                }
                if (btn) {
                    if (i === step) {
                        btn.className = 'btn btn-outline-primary w-100 py-2 active step-nav-btn';
                    } else if (i < step) {
                        btn.className = 'btn btn-outline-success w-100 py-2 step-nav-btn';
                    } else {
                        btn.className = 'btn btn-outline-secondary w-100 py-2 step-nav-btn';
                    }
                }
            }
        },

        loadCourseFiles: function() {
            const container = document.getElementById('course-files-list');
            if (!container) return;

            container.innerHTML = '<div class="p-3 text-center text-muted small">در حال دریافت فایل‌ها از سیستم ذخیره‌سازی مودل...</div>';

            Ajax.call([{
                methodname: 'local_aiquizgen_scan_resources',
                args: { courseid: courseId },
                done: function(response) {
                    if (response.status && response.files && response.files.length > 0) {
                        let html = '';
                        response.files.forEach(f => {
                            html += `
                                <label class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <input class="form-check-input course-file-check" type="checkbox" value="${f.id}" checked>
                                        <span class="small fw-bold">${f.filename}</span>
                                    </div>
                                    <span class="badge bg-light text-muted border">${f.filesizeformatted}</span>
                                </label>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div class="p-3 text-center text-muted small">هیچ فایل یا منبع بارگذاری‌شده‌ای در این درس یافت نشد. می‌توانید نکات را به صورت دستی در کادر پایین وارد کنید.</div>';
                    }
                },
                fail: function(err) {
                    container.innerHTML = '<div class="p-3 text-center text-danger small">خطا در دریافت فایل‌ها</div>';
                    Notification.exception(err);
                }
            }]);
        },

        generateQuestions: function() {
            const self = this;
            const btn = document.getElementById('btn-generate-ai');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'در حال ارتباط با هسته هوش مصنوعی مودل... ⏳';

            const selectedFiles = [];
            document.querySelectorAll('.course-file-check:checked').forEach(c => {
                selectedFiles.push(parseInt(c.value, 10));
            });

            const prompt = document.getElementById('teacher-custom-prompt') ? document.getElementById('teacher-custom-prompt').value : '';
            const qcount = parseInt(document.getElementById('input-qcount').value, 10);
            const difficultyEl = document.querySelector('input[name="difficulty"]:checked');
            const difficulty = difficultyEl ? difficultyEl.value : 'analytical';

            const qtypes = [];
            document.querySelectorAll('.qtype-check:checked').forEach(c => qtypes.push(c.value));

            Ajax.call([{
                methodname: 'local_aiquizgen_generate_questions',
                args: {
                    courseid: courseId,
                    prompt: prompt,
                    fileids: selectedFiles,
                    qcount: qcount,
                    difficulty: difficulty,
                    qtypes: qtypes,
                    lang: 'fa'
                },
                done: function(res) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    if (res.status && res.questionsjson) {
                        try {
                            questionsState = JSON.parse(res.questionsjson);
                            self.renderPreviewQuestions();
                            self.showStep(3);
                        } catch (e) {
                            Notification.addNotification({ message: 'خطا در خواندن پاسخ ساختاریافته هوش مصنوعی', type: 'error' });
                        }
                    }
                },
                fail: function(err) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    Notification.exception(err);
                }
            }]);
        },

        renderPreviewQuestions: function() {
            const container = document.getElementById('generated-questions-container');
            const badge = document.getElementById('preview-count-badge');
            if (badge) badge.textContent = `${questionsState.length} سوال`;
            if (!container) return;

            let html = '';
            questionsState.forEach((q, index) => {
                html += `
                    <div class="card p-3 shadow-sm border-start border-4 border-primary" data-index="${index}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-primary">سوال ${index + 1} (${q.qtype})</span>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-danger btn-sm btn-delete-q" data-index="${index}">🗑️ حذف</button>
                            </div>
                        </div>
                        <div class="mb-2 fw-bold small" contenteditable="true" onblur="Wizard.updateQText(${index}, this.innerText)">
                            ${q.questiontext}
                        </div>
                `;

                if (q.answers && q.answers.length > 0) {
                    html += '<div class="vstack gap-1 small mb-2">';
                    q.answers.forEach(a => {
                        const isCorrect = a.iscorrect ? 'text-success fw-bold' : 'text-muted';
                        html += `<div class="${isCorrect}">• ${a.text} ${a.iscorrect ? '✓ (صحیح)' : ''}</div>`;
                    });
                    html += '</div>';
                }

                if (q.generalfeedback) {
                    html += `<div class="p-2 bg-light rounded text-muted small border mb-2">💡 <b>توضیح:</b> ${q.generalfeedback}</div>`;
                }

                html += `
                        <div class="input-group input-group-sm mt-2">
                            <input type="text" class="form-control reprompt-input" placeholder="دستور بازتولید با هوش مصنوعی برای این سوال..." id="reprompt-input-${index}">
                            <button class="btn btn-outline-primary btn-reprompt" type="button" data-index="${index}">🔄 بازتولید این سوال</button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            // Bind single question delete
            container.querySelectorAll('.btn-delete-q').forEach(b => {
                b.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-index'), 10);
                    questionsState.splice(idx, 1);
                    Wizard.renderPreviewQuestions();
                });
            });

            // Bind single question reprompt
            container.querySelectorAll('.btn-reprompt').forEach(b => {
                b.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-index'), 10);
                    const inp = document.getElementById(`reprompt-input-${idx}`);
                    const instruction = inp ? inp.value.trim() : '';
                    if (!instruction) return;

                    b.disabled = true;
                    b.textContent = 'در حال بازتولید...';

                    Ajax.call([{
                        methodname: 'local_aiquizgen_reprompt_question',
                        args: {
                            courseid: courseId,
                            currentquestionjson: JSON.stringify(questionsState[idx]),
                            instruction: instruction
                        },
                        done: function(res) {
                            b.disabled = false;
                            b.textContent = '🔄 بازتولید این سوال';
                            if (res.status && res.questionjson) {
                                questionsState[idx] = JSON.parse(res.questionjson);
                                Wizard.renderPreviewQuestions();
                            }
                        },
                        fail: function(err) {
                            b.disabled = false;
                            b.textContent = '🔄 بازتولید این سوال';
                            Notification.exception(err);
                        }
                    }]);
                });
            });
        },

        updateQText: function(index, text) {
            if (questionsState[index]) {
                questionsState[index].questiontext = text;
            }
        },

        publishQuiz: function() {
            const btn = document.getElementById('btn-publish-final');
            btn.disabled = true;
            btn.innerHTML = 'در حال ساخت آزمون در مودل... ⏳';

            const quizName = document.getElementById('final-quiz-name').value;
            const categoryName = document.getElementById('final-category-name').value;
            const timelimit = parseInt(document.getElementById('final-timelimit').value, 10);
            const attempts = parseInt(document.getElementById('final-attempts').value, 10);
            const behaviour = document.getElementById('final-behaviour').value;

            Ajax.call([{
                methodname: 'local_aiquizgen_publish_quiz',
                args: {
                    courseid: courseId,
                    quizname: quizName,
                    categoryname: categoryName,
                    questionsjson: JSON.stringify(questionsState),
                    timelimit: timelimit,
                    attempts: attempts,
                    preferredbehaviour: behaviour
                },
                done: function(res) {
                    btn.classList.add('d-none');
                    const alert = document.getElementById('publish-result-alert');
                    const msg = document.getElementById('publish-result-msg');
                    const link = document.getElementById('publish-quiz-link');
                    if (alert && msg && link) {
                        msg.textContent = `${res.questionscount} سوال در بانک سوالات ایجاد شد و آزمون ساخته شد.`;
                        link.href = res.quizurl;
                        alert.classList.remove('d-none');
                    }
                },
                fail: function(err) {
                    btn.disabled = false;
                    btn.innerHTML = '✅ تأیید نهایی استاد و ساخت آزمون در مودل';
                    Notification.exception(err);
                }
            }]);
        }
    };

    window.Wizard = Wizard;
    return Wizard;
});
