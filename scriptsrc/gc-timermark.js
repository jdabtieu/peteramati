// gc-timermark.js -- Peteramati JavaScript library
// Peteramati is Copyright (c) 2006-2020 Eddie Kohler
// See LICENSE for open-source distribution terms

import { GradeClass } from "./gc.js";
import { GradeSheet } from "./gradeentry.js";
import { handle_ui } from "./ui.js";
import { sprintf, strftime } from "./utils.js";


const timefmt = "%Y-%m-%d %H:%M";

GradeClass.add("timermark", {
    text: function (v) {
        if (v == null || v === 0) {
            return "–";
        } else {
            return strftime(timefmt, v);
        }
    },
    simple_text: GradeClass.basic_text,
    tcell: function (v) {
        if (v == null || v === 0) {
            return "";
        } else {
            return strftime(timefmt, v);
        }
    },
    tcell_width: 10,
    make_compare: function (col) {
        const gidx = col.gidx;
        return function (a, b) {
            const ag = a.grades && a.grades[gidx],
                bg = b.grades && b.grades[gidx];
            if (ag === "" || ag == null || ag == 0 || bg === "" || bg == null || bg == 0) {
                if (ag !== "" && ag != null && ag != 0) {
                    return -1;
                } else if (bg !== "" && bg != null && bg != 0) {
                    return 1;
                }
            } else if (ag < bg) {
                return -1;
            } else if (ag > bg) {
                return 1;
            }
            return a._sort_user.localeCompare(b._sort_user);
        };
    },
    entry: function () {
        let t = '<button class="ui js-timermark hidden mr-2" type="button" name="'.concat(this.key, ':b" value="1">Press to start</button>');
        if (siteinfo.user.is_pclike) {
            t = t.concat('<button class="ui js-timermark hidden mr-2" type="button" name="', this.key, ':r" value="0">Reset</button>');
        }
        t = t.concat('<span class="pa-timermark-result hidden"></span><input type="hidden" class="uich pa-gradevalue" name="', this.key, '">');
        return t;
    },
    reflect_value: function (elt, g) {
        this._timeout = this.timeout;
        if (this.timeout_entry) {
            let gs = GradeSheet.closest(elt), ge, gv;
            if (gs
                && (ge = gs.entries[this.timeout_entry])
                && (gv = gs.grade_value(ge)) != null) {
                this._timeout = gv;
            }
        }
        const pd = elt.closest(".pa-pd");
        pd.querySelectorAll(".js-timermark").forEach(function (e) {
            e.classList.toggle("hidden", !g !== (e.value === "1"));
        });
        const tm = pd.querySelector(".pa-timermark-result");
        tm.classList.toggle("hidden", !g && !this._timeout);
        const to = $(elt).data("pa-timermark-interval");
        to && clearInterval(to);
        if (g
            && this._timeout
            && g + this._timeout > +document.body.getAttribute("data-now")) {
            timermark_interval(this, tm, g);
            $(elt).data("pa-timermark-interval", setInterval(timermark_interval, 15000, this, tm, g));
        } else if (g) {
            let t = strftime(timefmt, g);
            if (this._all && (this._all.updateat || 0) > g) {
                const delta = this._all.updateat - g;
                t += sprintf(" (updated %dh%dm later at %s)", delta / 3600, (delta / 60) % 60, strftime(timefmt, this._all.updateat));
            }
            tm.innerHTML = t;
        } else if (this._timeout) {
            tm.innerHTML = "Time once started: " + sec2text(this._timeout);
        }
    },
    justify: "left",
    sort: "forward"
});

handle_ui.on("js-timermark", function () {
    const colon = this.name.indexOf(":"),
        name = this.name.substring(0, colon),
        elt = this.closest("form").elements[name];
    elt.value = this.value;
    $(elt).trigger("change");
});

function sec2text(s) {
    if (s >= 3600 && s % 900 == 0) {
        return (s / 3600) + "h";
    } else if (s >= 3600) {
        return sprintf("%dh%dm", s / 3600, (s / 60) % 60);
    } else if (s > 360) {
        return sprintf("%dm", s / 60);
    } else {
        return sprintf("%dm%ds", s / 60, s % 60);
    }
}

function timermark_interval(ge, tm, gv) {
    const delta = +document.body.getAttribute("data-time-skew"),
        left = gv + ge._timeout - new Date().getTime() / 1000 + delta;
    let t = strftime(timefmt, gv);
    if (left > 360) {
        t = t.concat(" (", sec2text(left), " left)");
    } else if (left > 0) {
        t = t.concat(" <strong class=\"overdue\">(", sec2text(left), " left)</strong>");
    }
    tm.innerHTML = t;
}
