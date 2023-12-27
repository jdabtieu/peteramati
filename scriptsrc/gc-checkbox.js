// gc-checkbox.js -- Peteramati JavaScript library
// Peteramati is Copyright (c) 2006-2021 Eddie Kohler
// See LICENSE for open-source distribution terms

import { GradeClass } from "./gc.js";
import { GradeEntry } from "./gradeentry.js";
import { hasClass, addClass, removeClass, handle_ui } from "./ui.js";


GradeClass.add("checkbox", {
    text: function (v) {
        if (v == null || v === 0 || v === false) {
            return "–";
        } else if (v === (this.max || 1) || v === true) {
            return "✓";
        } else {
            return "" + v;
        }
    },
    simple_text: GradeClass.basic_text,
    tcell: function (v) {
        if (v == null || v === 0 || v === false) {
            return "";
        } else if (v === (this.max || 1) || v === true) {
            return "✓";
        } else {
            return "" + v;
        }
    },
    mount_edit: function (elt, id) {
        const ch = document.createElement("input");
        ch.type = "checkbox";
        ch.className = "uic uich pa-gradevalue ml-0 pa-fresh";
        ch.name = this.key;
        ch.id = id;
        ch.value = this.max;
        ch.disabled = this.disabled;
        const chsp = document.createElement("span");
        chsp.className = "pa-gradewidth";
        chsp.append(ch);
        return Checkbox_GradeClass.finish_mount_edit(this, chsp);
    },
    update_edit: function (elt, v, opts) {
        const want_checkbox = v == null || v === "" || v === 0 || (this && v === this.max),
            ve = elt.firstChild.firstChild;
        if (!want_checkbox && ve.type === "checkbox") {
            Checkbox_GradeClass.uncheckbox(ve);
        } else if (want_checkbox && ve.type !== "checkbox" && opts.reset) {
            Checkbox_GradeClass.recheckbox(ve);
        }
        if (ve.type === "checkbox") {
            ve.checked = !!v;
            ve.indeterminate = !!opts.mixed;
        } else if (ve.value !== v && (opts.reset || !$(ve).is(":focus"))) {
            ve.value = "" + v;
        }
    },
    justify: "center"
});

export class Checkbox_GradeClass {
    static uncheckbox(element) {
        const ge = GradeEntry.closest(element);
        if (element.type === "checkbox") {
            element.value = element.checked ? ge.max : "";
        }
        element.type = "text";
        removeClass(element, "ml-0");
        addClass(element, "pa-gradewidth");
        removeClass(element, "uic");
        hasClass(element, "uich") && addClass(element, "uii");
        const container = element.closest(".pa-pv");
        $(container).find(".pa-grade-uncheckbox").remove();
        $(container).find("input[name^=\"" + element.name + ":\"]").addClass("hidden");
    }
    static recheckbox(element) {
        const v = element.value.trim(), ge = GradeEntry.closest(element);
        element.type = "checkbox";
        element.checked = v !== "" && v !== "0";
        element.value = ge.max;
        addClass(element, "ml-0");
        removeClass(element, "pa-gradewidth");
        removeClass(element, "uii");
        hasClass(element, "uich") && addClass(element, "uic");
        $(element.closest(".pa-pv")).find(".pa-gradedesc").append(' <button type="button" class="qo ui pa-grade-uncheckbox" tabindex="-1">#</button>');
    }
    static finish_mount_edit(ge, chsp) {
        const sp = document.createElement("span");
        sp.className = "pa-gradedesc";
        const a = document.createElement("button");
        a.type = "button";
        a.className = "link x ui pa-grade-uncheckbox";
        a.tabIndex = -1;
        a.append("#");
        sp.append("of " + ge.max + " ", a);
        const fr = new DocumentFragment;
        fr.append(chsp, " ", sp);
        return fr;
    }
}

handle_ui.on("pa-grade-uncheckbox", function () {
    $(this.closest(".pa-pv")).find(".pa-gradevalue").each(function () {
        Checkbox_GradeClass.uncheckbox(this);
        this.focus();
        this.select();
    });
});
