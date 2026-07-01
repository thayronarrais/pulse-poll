/**
 * Alpine component for the public voter screen.
 *
 * Receives initial config/state from Blade and then derives all UI state from
 * Echo events broadcast on the public `live-session.{slug}` channel.
 */
export function liveVoter(config) {
    return {
        slug: config.slug,
        voteUrl: config.voteUrl,
        identifyUrl: config.identifyUrl,
        csrf: config.csrf,
        sessionStatus: config.state.session_status,
        question: config.state.question,
        hasVoted: config.state.has_voted,
        tally: config.state.tally,
        masterPicks: config.state.master_picks || {},
        voterName: config.voterName || '',
        entered: false,
        photoFile: null,
        photoPreview: null,
        myChoice: null,
        submitting: false,

        init() {
            window.Echo.channel('live-session.' + this.slug)
                .listen('.question.opened', (e) => {
                    this.question = {
                        id: e.question_id,
                        text: e.text || null,
                        choice_type: e.choice_type,
                        choices: e.choices,
                        status: 'open',
                    };
                    this.hasVoted = false;
                    this.tally = null;
                    this.masterPicks = {};
                    this.myChoice = null;
                    this.sessionStatus = 'live';
                })
                .listen('.question.text-updated', (e) => {
                    if (this.question && e.question_id === this.question.id) {
                        this.question = { ...this.question, text: e.text || null };
                    }
                })
                .listen('.results.updated', (e) => {
                    if (this.question && e.question_id === this.question.id) {
                        this.tally = e.tally;
                    }
                })
                .listen('.master.voted', (e) => {
                    if (!this.question || e.question_id !== this.question.id) return;
                    const list = this.masterPicks[e.choice] ? [...this.masterPicks[e.choice]] : [];
                    if (!list.some((m) => m.name === e.name)) {
                        list.push({ name: e.name, photo_url: e.photo_url });
                    }
                    this.masterPicks = { ...this.masterPicks, [e.choice]: list };
                })
                .listen('.question.closed', (e) => {
                    if (this.question && e.question_id === this.question.id) {
                        this.question.status = 'closed';
                        this.tally = e.tally;
                    }
                })
                .listen('.session.state', (e) => {
                    this.sessionStatus = e.status;
                    if (e.status === 'finished') {
                        this.question = null;
                    }
                });
        },

        setPhoto(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.photoFile = file;
            this.photoPreview = URL.createObjectURL(file);
        },

        // Register the optional identification once, then enter the question area.
        async enter() {
            if (this.submitting) return;
            this.voterName = this.voterName.trim();

            if (this.voterName) {
                this.submitting = true;
                const form = new FormData();
                form.append('name', this.voterName);
                if (this.photoFile) form.append('photo', this.photoFile);
                try {
                    const res = await fetch(this.identifyUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                        credentials: 'same-origin',
                        body: form,
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (data.photo_url) this.photoPreview = data.photo_url;
                    }
                } catch (e) {
                    // Identification is optional; enter anyway.
                }
                this.submitting = false;
            }

            this.entered = true;
        },

        // intro | waiting | open | voted | results | finished
        get screen() {
            if (!this.entered) return 'intro';
            if (this.sessionStatus === 'finished') return 'finished';
            if (!this.question) return 'waiting';
            if (this.question.status === 'closed') return 'results';
            if (this.hasVoted) return 'voted';
            return 'open';
        },

        get total() {
            return this.tally ? Object.values(this.tally).reduce((a, b) => a + b, 0) : 0;
        },

        pct(choice) {
            return this.total ? Math.round(((this.tally[choice] ?? 0) / this.total) * 100) : 0;
        },

        async vote(choice) {
            if (this.submitting || this.hasVoted) return;
            this.submitting = true;
            this.myChoice = choice;
            try {
                const res = await fetch(this.voteUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ choice, name: this.voterName.trim() || null }),
                });
                if (res.ok || res.status === 409) {
                    // 409 = already voted on this device, or voting closed: lock anyway.
                    this.hasVoted = true;
                } else {
                    this.myChoice = null;
                }
            } catch (e) {
                this.myChoice = null;
            }
            this.submitting = false;
        },
    };
}
