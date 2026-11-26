
// Builds the admin quiz editor, validates quiz drafts, and submits quiz settings.
(() => {
    const $ = s => document.querySelector(s);

    const esc = s => String(s).replace(/[&<>"]/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;'
    }[c]));

    const D = window.QUIZ;
    const Q = D.quizzes;

    const nq = () => ({
        type: 'mc',
        q: '',
        ch: ['', '', '', ''],
        ok: -1,
        ans: ''
    });

    const blank = () => ({
        title: '',
        timer: 30,
        points: 10,
        xp_per_correct: 5,
        due: D.today,
        assign: 'BSCS 2B',
        allow_retake: 0,
        show_answer_feedback: 0,
        qs: [nq()],
        i: 0
    });

    const clone = o => JSON.parse(JSON.stringify(o));
    const KEY = 'quizDraft';

    if (D.clearDraft) {
        try {
            sessionStorage.removeItem(KEY);
        } catch (e) {}
    }

    const load = () => {
        if (D.editQuiz) {
            const d = clone(D.editQuiz);

            d.i = 0;
            d.timer = Number(d.timer || 30);
            d.points = Number(d.points || 10);
            d.xp_per_correct = Number(d.xp_per_correct || 5);
            d.allow_retake = Number(d.allow_retake || 0);
            d.show_answer_feedback = Number(d.show_answer_feedback || 0);
            d.qs = Array.isArray(d.qs) && d.qs.length
                ? d.qs
                : [nq()];

            return d;
        }

        try {
            const d = JSON.parse(sessionStorage.getItem(KEY));

            if (d && Array.isArray(d.qs) && d.qs.length) {
                d.i = Math.min(d.i || 0, d.qs.length - 1);
                d.xp_per_correct = Number(d.xp_per_correct || 5);
                d.show_answer_feedback = Number(d.show_answer_feedback || 0);
                return d;
            }
        } catch (e) {}

        return blank();
    };

    const keep = () => {
        try {
            sessionStorage.setItem(KEY, JSON.stringify(S.draft));
        } catch (e) {}
    };

    let M = null;

    const S = {
        draft: load(),
        editId: D.editQuiz ? Number(D.editQuiz.quizId) : 0
    };

    const fmt = d => d
        ? new Date(d + 'T00:00').toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        })
        : 'No date';

    const pts = z => z.qs.length * z.points;

    const meta = z =>
        `${z.qs.length} question${z.qs.length > 1 ? 's' : ''} · ${pts(z)} pts · Due ${fmt(z.due)} · ${z.assign}`;

    const sc = s => s === 'm' ? M.z : S.draft;
    const redraw = s => s === 'm' ? editModal() : drawBuilder();

    function post(url, data) {
        const f = $('#postForm');

        f.action = url;

        ['quiz', 'id', 'mode', 'publish', 'allow_retake', 'show_answer_feedback'].forEach(k => {
            f.elements[k].value = data[k] ?? '';
        });

        // If the setting is inside the quiz JSON, copy it
        // to the hidden form field as well.
        if (data.show_answer_feedback === undefined && data.quiz) {
            try {
                const quizData = JSON.parse(data.quiz);

                f.elements['show_answer_feedback'].value =
                    quizData.show_answer_feedback ?? 0;

            } catch (e) {
                f.elements['show_answer_feedback'].value = 0;
            }
        }

        f.submit();
    }

    const qErr = q => {
        if (!q.q.trim()) return 'Type the question first.';

        if (q.type === 'mc' || q.type === 'tf') {
            const optionCount = q.type === 'tf' ? 2 : 4;

            if (q.ch.slice(0, optionCount).some(c => !String(c).trim())) {
                return q.type === 'tf'
                    ? 'Choose the correct True or False answer.'
                    : 'Fill in all four choices (A to D).';
            }

            if (q.ok < 0 || q.ok >= optionCount) {
                return 'Select the correct answer.';
            }

            return '';
        }

        return !String(q.ans).trim()
            ? 'Enter the expected answer.'
            : '';
    };

    function check(z) {
        if (!z.title.trim()) {
            return { m: 'Give your quiz a title first.', t: 1 };
        }

        if (!z.due) {
            return { m: 'Pick a due date.', t: 1 };
        }

        if (!Number.isInteger(Number(z.xp_per_correct)) ||
            Number(z.xp_per_correct) < 1 ||
            Number(z.xp_per_correct) > 1000) {
            return {
                m: 'XP per correct answer must be between 1 and 1000.',
                t: 1
            };
        }

        for (let k = 0; k < z.qs.length; k++) {
            const e = qErr(z.qs[k]);

            if (e) {
                return {
                    m: `Question ${k + 1}: ${e}`,
                    q: k
                };
            }
        }

        return null;
    }

    function qe(o, s) {
        const q = o.qs[o.i];

        const ty = (t, n, d) => `
            <button class="type ${q.type === t ? 'on' : ''}"
                data-a="type" data-s="${s}" data-t="${t}">
                <b>${n}</b>
                <small>${d}</small>
            </button>
        `;

        return `
            <div class="lbl">Question type</div>

            <div class="types">
                ${ty('mc', 'Multiple choice', 'Add choices A to D and mark the correct one')}
                ${ty('tf', 'True or False', 'Students choose True or False')}
                ${ty('id', 'Identification', 'Set the expected answer')}
            </div>

            <div class="lbl">Question</div>

            <textarea data-s="${s}" data-f="q"
                placeholder="Type the question…">${esc(q.q)}</textarea>
        ` +
        (q.type === 'mc'
            ? `
                <div class="lbl">Choices (select the correct answer)</div>

                ${['A', 'B', 'C', 'D'].map((L, n) => `
                    <div class="ch">
                        <span class="L">${L}</span>

                        <input
                            class="${q.ok === n ? 'ok' : ''}"
                            data-s="${s}"
                            data-f="ch${n}"
                            value="${esc(q.ch[n])}">

                        <button class="rad ${q.ok === n ? 'on' : ''}"
                            data-a="ok"
                            data-s="${s}"
                            data-n="${n}"
                            aria-pressed="${q.ok === n}">
                            Correct
                        </button>
                    </div>
                `).join('')}
            `
            : q.type === 'tf'
                ? `
                    <div class="lbl">Correct answer</div>

                    <div class="types">
                        ${['True', 'False'].map((answer, n) => `
                            <button class="type ${q.ok === n ? 'on' : ''}"
                                data-a="ok" data-s="${s}" data-n="${n}"
                                aria-pressed="${q.ok === n}">
                                <b>${answer}</b>
                                <small>${q.ok === n ? 'Correct answer' : 'Mark as correct'}</small>
                            </button>
                        `).join('')}
                    </div>
                `
            : `
                <div class="lbl">Expected answer</div>

                <input data-s="${s}" data-f="ans"
                    value="${esc(q.ans)}"
                    placeholder="Type answer here…">

                <span class="hint">
                    Student answers are checked against this text (not case-sensitive).
                </span>
            `
        );
    }

    const qh = (z, s) => `
        <div class="qh">
            <h3>Question editor</h3>

            <div class="qp">
                ${z.qs.map((_, k) => `
                    <button class="q ${k === z.i ? 'on' : ''}"
                        data-a="goq" data-s="${s}" data-n="${k}">
                        Q${k + 1}
                    </button>
                `).join('')}

                <button class="q add" data-a="addq"
                    data-s="${s}" aria-label="Add question">
                    +
                </button>
            </div>

            <small>Question ${z.i + 1} of ${z.qs.length}</small>

            ${s === 'c' && z.qs.length > 1
                ? `<button class="del" style="margin:0" data-a="delq" data-s="c">Delete</button>`
                : ''}
        </div>
    `;

    function drawBuilder() {
        const b = $('#builder');
        if (!b) return;

        const d = S.draft;

        b.innerHTML =
            qh(d, 'c') +
            qe(d, 'c') +

            `
                <div class="g4" style="margin:20px 0; grid-template-columns:repeat(2,minmax(0,1fr));">
                    <div>
                        <div class="lbl">XP per correct answer</div>
                        <input type="number"
                            min="1"
                            max="1000"
                            step="1"
                            data-s="c"
                            data-f="xp_per_correct"
                            value="${d.xp_per_correct}">
                        <small class="hint">Game XP only. This does not affect quiz grades.</small>
                    </div>
                </div>

                <div style="margin:20px 0; display:flex; align-items:center; gap:10px;">
                    <input type="checkbox"
                        id="allowRetakeCheck"
                        data-s="c"
                        data-f="allow_retake"
                        value="1"
                        ${d.allow_retake ? 'checked' : ''}
                        style="width:18px; height:18px; cursor:pointer;">

                    <label for="allowRetakeCheck"
                        style="font-size:14px; font-weight:600; color:var(--ink); cursor:pointer;">
                        Allow students to retake this quiz
                    </label>
                </div>

                <div style="margin:20px 0; display:flex; align-items:center; gap:10px;">
                    <input type="checkbox"
                        id="showAnswerFeedbackCheck"
                        data-s="c"
                        data-f="show_answer_feedback"
                        value="1"
                        ${d.show_answer_feedback ? 'checked' : ''}
                        style="width:18px; height:18px; cursor:pointer;">

                    <label for="showAnswerFeedbackCheck"
                        style="font-size:14px; font-weight:600; color:var(--ink); cursor:pointer;">
                        Allow students to review their answers after submission
                    </label>
                </div>

                <div class="bar">
                    <button class="btn k" data-a="save">Save quiz</button>
                    <button class="btn ol" data-a="pubnew">Publish quiz</button>

                    ${d.i ? '<button class="btn r" data-a="back">← Back</button>' : ''}

                    <button class="btn o ${d.i ? '' : 'r'}" data-a="next">
                        Next →
                    </button>
                </div>
            `;

        keep();
    }

    const openM = (h, w) => {
        const m = $('#modal');

        m.className = 'modal' + (w ? ' wide' : '');
        m.innerHTML = h;

        $('#ov').hidden = false;
    };

    const close = () => {
        $('#ov').hidden = true;
        M = null;
    };

    function editModal() {
        const z = M.z;

        const opt = (a, v, f) => a.map(x => `
            <option value="${x}" ${v == x ? 'selected' : ''}>
                ${f(x)}
            </option>
        `).join('');

        openM(`
            <h2>Edit Quiz</h2>

            <p class="sub">
                Students will not be able to see and answer it right away.
            </p>

            <div class="lbl">Quiz title</div>

            <input data-s="m" data-f="title"
                value="${esc(z.title)}"
                placeholder="e.g. Intro to Computing">

            <div class="g4">
                <div>
                    <div class="lbl">Timer per question</div>
                    <select data-s="m" data-f="timer">
                        ${opt([15, 30, 45, 60, 90], z.timer, v => v + ' sec')}
                    </select>
                </div>

                <div>
                    <div class="lbl">Points per question</div>
                    <select data-s="m" data-f="points">
                        ${opt([5, 10, 15, 20], z.points, v => v + ' pts')}
                    </select>
                </div>

                <div>
                    <div class="lbl">XP per correct answer</div>
                    <input type="number"
                        min="1"
                        max="1000"
                        step="1"
                        data-s="m"
                        data-f="xp_per_correct"
                        value="${z.xp_per_correct}">
                    <small class="hint">Game XP only; does not affect grades.</small>
                </div>

                <div>
                    <div class="lbl">Due date</div>
                    <input type="date" data-s="m" data-f="due"
                        value="${z.due}">
                </div>

                <div>
                    <div class="lbl">Assign to</div>
                    <select data-s="m" data-f="assign">
                        ${opt(
                            ['BSCS 2A', 'BSCS 2B', 'BSCS 3A', 'BSCS 3B'],
                            z.assign,
                            v => v
                        )}
                    </select>
                </div>
            </div>

            <div style="margin:16px 0; display:flex; align-items:center; gap:10px;">
                <input type="checkbox"
                    id="modalAllowRetake"
                    data-s="m"
                    data-f="allow_retake"
                    value="1"
                    ${z.allow_retake ? 'checked' : ''}
                    style="width:18px; height:18px; cursor:pointer;">

                <label for="modalAllowRetake"
                    style="font-size:14px; font-weight:600; color:var(--ink); cursor:pointer;">
                    Allow students to retake this quiz
                </label>
            </div>

            <div style="margin:16px 0; display:flex; align-items:center; gap:10px;">
                <input type="checkbox"
                    id="modalShowAnswerFeedback"
                    data-s="m"
                    data-f="show_answer_feedback"
                    value="1"
                    ${z.show_answer_feedback ? 'checked' : ''}
                    style="width:18px; height:18px; cursor:pointer;">

                <label for="modalShowAnswerFeedback"
                    style="font-size:14px; font-weight:600; color:var(--ink); cursor:pointer;">
                    Allow students to review their answers after submission
                </label>
            </div>

            ${qh(z, 'm')}
            ${qe(z, 'm')}

            <div class="bar">
                <button class="del" data-a="delq">
                    Delete question
                </button>

                <button class="btn" data-a="close">Cancel</button>

                <button class="btn o" data-a="done">Done</button>
            </div>
        `, 1);
    }

    function confirmM(pub) {
        const z = M.z;

        const checkboxHtml = pub ? `
            <div style="margin-top:12px; display:flex; align-items:center; gap:8px; border-top:1px solid var(--border); padding-top:10px;">
                <input type="checkbox"
                    id="modalAllowRetakePub"
                    data-s="m"
                    data-f="allow_retake"
                    value="1"
                    ${z.allow_retake ? 'checked' : ''}
                    style="width:16px; height:16px; cursor:pointer;">

                <label for="modalAllowRetakePub"
                    style="font-size:13px; font-weight:600; color:var(--ink); cursor:pointer;">
                    Allow students to retake this quiz
                </label>
            </div>

            <div style="margin-top:10px; display:flex; align-items:center; gap:8px;">
                <input type="checkbox"
                    id="modalShowAnswerFeedbackPub"
                    data-s="m"
                    data-f="show_answer_feedback"
                    value="1"
                    ${z.show_answer_feedback ? 'checked' : ''}
                    style="width:16px; height:16px; cursor:pointer;">

                <label for="modalShowAnswerFeedbackPub"
                    style="font-size:13px; font-weight:600; color:var(--ink); cursor:pointer;">
                    Allow students to review their answers after submission
                </label>
            </div>
        ` : '';

        openM(`
            <h2>${pub ? 'Publish' : 'Unpublish'} this quiz?</h2>

            <p class="sub">
                ${pub
                    ? 'Students will be able to see and answer it right away.'
                    : 'Students will no longer be able to see or answer it.'}
            </p>

            <div class="row" style="display:block; margin:12px 0 18px; padding:14px">
                <b>${esc(z.title)}</b>

                <small style="font-size:12px; margin-top:6px">
                    ${z.qs.length} question${z.qs.length > 1 ? 's' : ''} ·
                    ${pts(z)} pts · ${z.timer} sec per question
                </small>

                <small style="font-size:12px; margin-top:4px">
                    ${z.xp_per_correct} XP per correct answer
                </small>

                <small style="font-size:12px; margin-top:4px">
                    Due ${fmt(z.due)} · Assigned to ${esc(z.assign)}
                </small>

                ${checkboxHtml}
            </div>

            <div class="bar" style="justify-content:flex-end; padding:0">
                <button class="btn" data-a="close">Cancel</button>

                <button class="btn o" data-a="go">
                    ${pub ? 'Publish quiz' : 'Unpublish quiz'}
                </button>
            </div>
        `);
    }

    function delM(z) {
        openM(`
            <h2>Delete this quiz?</h2>

            <p class="sub">This can’t be undone.</p>

            <div class="row" style="display:block; margin:12px 0 18px; padding:14px">
                <b>${esc(z.title)}</b>
                <small style="font-size:12px; margin-top:6px">
                    ${meta(z)}
                </small>
            </div>

            <div class="bar" style="justify-content:flex-end; padding:0">
                <button class="btn" data-a="close">Cancel</button>
                <button class="btn dl fill" data-a="go">Delete quiz</button>
            </div>
        `);
    }

    function resultsM(z) {
        openM(`
            <h2>Results · ${esc(z.title)}</h2>

            <p class="sub">
                ${z.sub} of ${z.tot} students have submitted.
            </p>
        ` +
        (z.sub
            ? `
                <div class="g4" style="grid-template-columns:repeat(3,1fr)">
                    ${[
                        ['Submitted', z.sub + '/' + z.tot],
                        ['Average score', z.avg + '%'],
                        ['Pending', z.tot - z.sub]
                    ].map(([l, v]) => `
                        <div class="st" style="background:var(--field)">
                            <small>${l}</small>
                            <b>${v}</b>
                        </div>
                    `).join('')}
                </div>

                <div style="height:8px; background:var(--field); border-radius:9px; margin:14px 0">
                    <i style="display:block; height:100%; width:${z.tot ? z.sub / z.tot * 100 : 0}%; background:var(--o); border-radius:9px"></i>
                </div>
            `
            : '<p class="note">No submissions yet.</p>'
        ) + `
            <div class="bar" style="justify-content:flex-end; padding:0">
                <button class="btn o" data-a="close">Close</button>
            </div>
        `);
    }

    const fail = e => {
        if (e.q != null) S.draft.i = e.q;

        if (e.t) {
            M = {
                z: clone(S.draft),
                src: 'draft'
            };

            editModal();
        } else {
            drawBuilder();
        }

        toast(e.m);
    };

    const A = {
        type: d => {
            const o = sc(d.s);
            const q = o.qs[o.i];
            q.type = d.t;

            if (d.t === 'tf') {
                q.ch = ['True', 'False', '', ''];
                q.ok = -1;
            } else if (d.t === 'mc') {
                q.ch = Array.from({ length: 4 }, (_, n) => q.ch[n] || '');
                q.ok = -1;
            }

            redraw(d.s);
        },

        ok: d => {
            const o = sc(d.s);
            o.qs[o.i].ok = Number(d.n);
            redraw(d.s);
        },

        goq: d => {
            sc(d.s).i = Number(d.n);
            redraw(d.s);
        },

        addq: d => {
            const z = sc(d.s);
            const e = qErr(z.qs[z.i]);

            if (e) return toast(`Question ${z.i + 1}: ${e}`);

            z.qs.push(nq());
            z.i = z.qs.length - 1;

            redraw(d.s);
            toast(`Question ${z.qs.length} added.`);
        },

        delq: d => {
            const z = sc(d.s);

            if (z.qs.length < 2) {
                return toast('A quiz needs at least one question.');
            }

            z.qs.splice(z.i, 1);
            z.i = Math.max(0, z.i - 1);

            redraw(d.s);
            toast('Question deleted.');
        },

        close: close,

        done: () => {
            const e = check(M.z);

            if (e) {
                if (e.q != null) M.z.i = e.q;
                editModal();
                return toast(e.m);
            }

            if (M.src === 'draft') {
                S.draft = M.z;
                close();
                drawBuilder();
                toast('Quiz details updated.');
            } else {
                post('update_quiz.php', {
                    id: M.id,
                    quiz: JSON.stringify(M.z),
                    allow_retake: M.z.allow_retake,
                    show_answer_feedback: M.z.show_answer_feedback
                });
            }
        },

        next: () => {
            const d = S.draft;
            const e = qErr(d.qs[d.i]);

            if (e) return toast(`Question ${d.i + 1}: ${e}`);

            if (d.i < d.qs.length - 1) {
                d.i++;
                drawBuilder();
            } else {
                A.addq({ s: 'c' });
            }
        },

        back: () => {
            S.draft.i--;
            drawBuilder();
        },

        details: () => {
            M = {
                z: clone(S.draft),
                src: 'draft'
            };

            editModal();
        },

        save: () => {
            const e = check(S.draft);
            if (e) return fail(e);

            const data = {
                quiz: JSON.stringify(S.draft),
                allow_retake: S.draft.allow_retake,
                show_answer_feedback: S.draft.show_answer_feedback
            };

            if (S.editId) {
                data.id = S.editId;
                post('update_quiz.php', data);
            } else {
                post('save_quiz.php', data);
            }
        },

        pubnew: () => {
            const e = check(S.draft);
            if (e) return fail(e);

            M = {
                z: clone(S.draft),
                src: 'pubnew'
            };

            confirmM(true);
        },

        pubsaved: d => {
            M = {
                z: clone(Q[d.id]),
                src: 'pubsaved',
                id: d.id
            };

            M.z.show_answer_feedback = Number(M.z.show_answer_feedback || 0);

            confirmM(true);
        },

        unpub: d => {
            M = {
                z: clone(Q[d.id]),
                src: 'unpub',
                id: d.id
            };

            confirmM(false);
        },

        edit: d => {
            M = {
                z: clone(Q[d.id]),
                src: 'saved',
                id: d.id
            };

            M.z.i = 0;
            M.z.xp_per_correct = Number(M.z.xp_per_correct || 5);
            M.z.allow_retake = Number(M.z.allow_retake || 0);
            M.z.show_answer_feedback = Number(M.z.show_answer_feedback || 0);

            editModal();
        },

        delsaved: d => {
            M = {
                z: clone(Q[d.id]),
                src: 'del',
                id: d.id
            };

            delM(M.z);
        },

        results: d => {
            const z = Q[d.id];

            resultsM({
                ...z,
                tot: z.tot || 0,
                avg: z.avg_score || 0
            });
        },

        new: () => {
            S.draft = blank();
            S.editId = 0;
            keep();
            location.href = 'quiz1.php?tab=create';
        },

        go: () => {
            const s = M.src;

            if (s === 'del') {
                post('delete_quiz.php', { id: M.id });

            } else if (s === 'unpub') {
                post('publish_quiz.php', {
                    id: M.id,
                    mode: 'unpublish'
                });

            } else if (s === 'pubsaved') {
                post('publish_quiz.php', {
                    id: M.id,
                    mode: 'publish',
                    allow_retake: M.z.allow_retake,
                    show_answer_feedback: M.z.show_answer_feedback
                });

            } else if (s === 'pubnew') {
                const data = {
                    quiz: JSON.stringify(M.z),
                    allow_retake: M.z.allow_retake,
                    show_answer_feedback: M.z.show_answer_feedback,
                    publish: '1'
                };

                if (S.editId) {
                    data.id = S.editId;
                    post('update_quiz.php', data);
                } else {
                    post('save_quiz.php', data);
                }
            }
        }
    };

    document.addEventListener('click', e => {
        const b = e.target.closest('[data-a]');

        if (b && A[b.dataset.a]) {
            A[b.dataset.a](b.dataset);
        } else if (e.target.id === 'ov') {
            close();
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !$('#ov').hidden) {
            close();
        }
    });

    function updateField(t) {
        const f = t.dataset.f;
        if (!f) return;

        const o = sc(t.dataset.s);

        if (f === 'allow_retake') {
            o.allow_retake = t.checked ? 1 : 0;

        } else if (f === 'show_answer_feedback') {
            o.show_answer_feedback = t.checked ? 1 : 0;

        } else if (
            ['title', 'timer', 'points', 'xp_per_correct', 'due', 'assign'].includes(f)
        ) {
            o[f] = ['timer', 'points', 'xp_per_correct'].includes(f)
                ? Number(t.value)
                : t.value;

        } else {
            const q = o.qs[o.i];

            if (f === 'q') {
                q.q = t.value;
            } else if (f === 'ans') {
                q.ans = t.value;
            } else if (f.startsWith('ch')) {
                q.ch[Number(f.slice(2))] = t.value;
            }
        }

        if (t.dataset.s === 'c') keep();
    }

    document.addEventListener('change', e => {
        if (e.target.dataset.f) updateField(e.target);
    });

    document.addEventListener('input', e => {
        if (e.target.dataset.f && e.target.type !== 'checkbox') {
            updateField(e.target);
        }
    });

    if (D.tab === 'create') {
        drawBuilder();
    }
})();
