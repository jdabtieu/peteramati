<?php
// diffmany.php -- Peteramati multidiff page
// HotCRP and Peteramati are Copyright (c) 2006-2017 Eddie Kohler and others
// See LICENSE for open-source distribution terms

require_once("src/initweb.php");
ContactView::set_path_request(["/p"]);
if ($Me->is_empty() || !$Me->isPC)
    $Me->escape();
global $Pset, $Qreq, $psetinfo_idx;
$Qreq = make_qreq();
$Pset = ContactView::find_pset_redirect($Qreq->pset);
if ($Qreq->file)
    $Qreq->files = [$Qreq->file];
else if ($Qreq->files && ($f = simplify_whitespace($Qreq->file)) !== "")
    $Qreq->files = explode(" ", $f);
$psetinfo_idx = 0;

function echo_diff_one(Contact $user, Pset $pset, Qrequest $qreq) {
    global $Me, $psetinfo_idx;
    ++$psetinfo_idx;
    $info = new PsetView($pset, $user, $Me);
    echo '<div id="pa-psetinfo', $psetinfo_idx, '" class="pa-psetinfo"',
        ' data-pa-pset="', htmlspecialchars($pset->urlkey),
        '" data-pa-user="', htmlspecialchars($Me->user_linkpart($user));
    if (!$pset->gitless && $info->grading_hash())
        echo '" data-pa-hash="', htmlspecialchars($info->grading_hash());
    if (!$pset->gitless && $pset->directory)
        echo '" data-pa-directory="', htmlspecialchars($pset->directory_slash);
    if ($Me->can_set_grades($pset, $info))
        echo '" data-pa-can-set-grades="yes';
    if ($info->user_can_view_grades())
        echo '" data-pa-user-can-view-grades="yes';
    if ($info->can_view_grades())
        echo '" data-pa-gradeinfo="', htmlspecialchars(json_encode($info->grade_json()));
    echo '">';

    $u = $Me->user_linkpart($user);
    if ($user !== $Me && !$user->is_anonymous && $user->contactImageId)
        echo '<img class="pa-smallface" src="' . hoturl("face", array("u" => $u, "imageid" => $user->contactImageId)) . '" />';

    echo '<h2 class="homeemail"><a href="',
        hoturl("pset", array("u" => $u, "pset" => $pset->urlkey)), '">', htmlspecialchars($u), '</a>';
    if ($user->extension)
        echo " (X)";
    /*if ($Me->privChair && $user->is_anonymous)
        echo " ",*/
    if ($Me->privChair)
        echo "&nbsp;", become_user_link($user);
    echo '</h2>';

    if ($user !== $Me && !$user->is_anonymous)
        echo '<h3>', Text::user_html($user), '</h3>';
    echo '<hr class="c" />';

    $lnorder = $info->viewable_line_notes();
    $allowfiles = $qreq->files;
    $diff = $info->repo->diff($pset, null, $info->grading_hash(), array("needfiles" => $lnorder->note_files(), "allowfiles" => $allowfiles));
    $info->expand_diff_for_grades($diff);
    if (count($allowfiles) == 1
        && isset($diff[$allowfiles[0]])
        && $qreq->lines
        && preg_match('/\A\s*(\d+)-(\d+)\s*\z/', $qreq->lines, $m))
        $diff[$allowfiles[0]] = $diff[$allowfiles[0]]->restrict_linea(intval($m[1]), intval($m[2]) + 1);

    foreach ($diff as $file => $dinfo)
        $info->echo_file_diff($file, $dinfo, $lnorder, true, count($qreq->files) == 1);

    if ($pset->has_grade_landmark)
        echo Ht::unstash_script('pa_loadgrades.call($("#pa-psetinfo' . $psetinfo_idx . '")[0], true)');
    echo "</div>\n";
    echo "<hr />\n";
}

$Conf->header(htmlspecialchars($Pset->title . " > " . join(" ", $Qreq->files)), "home");

foreach (explode(" ", $Qreq->users) as $user) {
    if ($user !== "" && ($user = $Conf->user_by_whatever($user))) {
        echo_diff_one($user, $Pset, $Qreq);
    } else if ($user !== "") {
        echo "<p>no such user ", htmlspecialchars($user), "</p>\n";
    }
}

Ht::stash_script('$(window).on("beforeunload",pa_beforeunload)');
echo "<div class='clear'></div>\n";
$Conf->footer();
