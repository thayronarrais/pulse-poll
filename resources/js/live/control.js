import qrcode from 'qrcode-generator';

/**
 * Alpine component for the admin control panel.
 *
 * Drives the open/close/start/finish controls and renders live tallies, which
 * update from the same public `live-session.{slug}` channel the voters use.
 */
export function liveControl(config) {
    return {
        slug: config.slug,
        csrf: config.csrf,
        startUrl: config.startUrl,
        finishUrl: config.finishUrl,
        askUrl: config.askUrl,
        questions: config.questions,
        participants: config.participants,
        toggleMasterUrlTemplate: config.toggleMasterUrlTemplate,
        participantUrl: config.participantUrl,
        linkCopied: false,
        qrSrc: '',
        sessionStatus: config.sessionStatus,
        currentQuestionId: config.currentQuestionId,
        responsesUrl: config.responsesUrl,
        responses: config.responses || {},
        selectedParticipantId: null,
        busy: false,

        // Generate the QR code locally (no external service) as a data URL.
        makeQr() {
            const qr = qrcode(0, 'M');
            qr.addData(this.participantUrl);
            qr.make();
            this.qrSrc = qr.createDataURL(6, 8);
        },

        async copyLink() {
            const text = this.participantUrl;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    // Fallback for non-secure contexts (e.g. http://*.test).
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                this.linkCopied = true;
                setTimeout(() => { this.linkCopied = false; }, 2000);
            } catch (e) {
                // Leave the visible link as a manual fallback.
            }
        },

        init() {
            this.makeQr();
            window.Echo.channel('live-session.' + this.slug)
                .listen('.results.updated', (e) => this.applyTally(e.question_id, e.tally))
                .listen('.question.closed', (e) => this.applyTally(e.question_id, e.tally))
                .listen('.participant.identified', (e) => this.upsertParticipant(e));
        },

        upsertParticipant(e) {
            const toggleUrl = this.toggleMasterUrlTemplate.replace('__ID__', e.id);
            const existing = this.participants.find((p) => p.id === e.id);
            if (existing) {
                existing.name = e.name;
                existing.photo_url = e.photo_url;
                existing.is_master = e.is_master;
            } else {
                this.participants.push({
                    id: e.id,
                    name: e.name,
                    photo_url: e.photo_url,
                    is_master: e.is_master,
                    toggleUrl,
                });
            }
        },

        async selectParticipant(p) {
            if (this.selectedParticipantId === p.id) {
                this.selectedParticipantId = null;
                return;
            }
            this.selectedParticipantId = p.id;
            await this.fetchResponses();
        },

        async fetchResponses() {
            try {
                const res = await fetch(this.responsesUrl, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    credentials: 'same-origin',
                });
                if (res.ok) this.responses = await res.json();
            } catch (e) {
                // Mantém o mapa carregado com a página como fallback.
            }
        },

        pickFor(q) {
            if (this.selectedParticipantId === null) return null;
            const r = this.responses[this.selectedParticipantId];
            return r ? (r[q.id] ?? null) : null;
        },

        selectedName() {
            const p = this.participants.find((x) => x.id === this.selectedParticipantId);
            return p ? p.name : '';
        },

        applyTally(questionId, tally) {
            const q = this.questions.find((q) => q.id === questionId);
            if (q) q.tally = tally;
        },

        total(q) {
            return q.tally ? Object.values(q.tally).reduce((a, b) => a + b, 0) : 0;
        },

        pct(q, choice) {
            const t = this.total(q);
            return t ? Math.round(((q.tally[choice] ?? 0) / t) * 100) : 0;
        },

        async post(url, body, method = 'POST') {
            if (this.busy) return null;
            this.busy = true;
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                    body: body ? JSON.stringify(body) : undefined,
                });
                return res.ok ? await res.json() : null;
            } finally {
                this.busy = false;
            }
        },

        async toggleMaster(p) {
            const data = await this.post(p.toggleUrl);
            if (data) p.is_master = data.is_master;
        },

        async ask(choiceType) {
            const question = await this.post(this.askUrl, { choice_type: choiceType });
            if (!question) return;
            this.sessionStatus = 'live';
            this.questions.forEach((item) => {
                if (item.status === 'open') item.status = 'closed';
            });
            // Newest question on top.
            this.questions.unshift(question);
            this.currentQuestionId = question.id;
        },

        startEdit(q) {
            q._draft = q.admin_text || '';
            q._editing = true;
        },

        cancelEdit(q) {
            q._editing = false;
        },

        async saveEdit(q) {
            const data = await this.post(q.updateUrl, { admin_text: q._draft }, 'PATCH');
            if (data) q.admin_text = data.admin_text;
            q._editing = false;
        },

        async start() {
            const data = await this.post(this.startUrl);
            if (data) this.sessionStatus = data.status;
        },

        async openQuestion(q) {
            const data = await this.post(q.openUrl);
            if (!data) return;
            this.sessionStatus = 'live';
            this.questions.forEach((item) => {
                if (item.status === 'open') item.status = 'closed';
            });
            q.status = 'open';
            this.currentQuestionId = q.id;
        },

        async closeQuestion(q) {
            const data = await this.post(q.closeUrl);
            if (!data) return;
            q.status = 'closed';
            if (data.tally) q.tally = data.tally;
        },

        async finish() {
            const data = await this.post(this.finishUrl);
            if (!data) return;
            this.sessionStatus = data.status;
            this.currentQuestionId = null;
            this.questions.forEach((item) => {
                if (item.status === 'open') item.status = 'closed';
            });
        },
    };
}
