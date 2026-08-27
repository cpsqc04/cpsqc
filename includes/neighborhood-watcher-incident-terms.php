<?php

const NW_INCIDENT_TERMS_VERSION = '2026-07-18-v1';

function getNwIncidentTermsVersion(): string
{
    return NW_INCIDENT_TERMS_VERSION;
}

function getNwIncidentTermsSummary(): string
{
    return 'I confirm this report is truthful, made in good faith, and not intended to harass or settle personal grudges. I understand false or malicious reports may lead to account review or suspension.';
}

function getNwIncidentTermsHtml(): string
{
    return '
        <p>Before submitting an incident report to the BPSO, you must read and agree to the following terms.</p>
        <h3>Truthful reporting</h3>
        <p>Reports must be based on observed facts and submitted in good faith for community safety purposes.</p>
        <h3>Accountability</h3>
        <p>Your identity and submission time are recorded. False, exaggerated, or malicious reports may result in account review, suspension, or referral to barangay authorities.</p>
        <h3>BPSO verification</h3>
        <p>The BPSO may verify your report before assigning personnel or taking action.</p>
        <h3>Updates</h3>
        <p>These terms may be updated from time to time. Submitting a report means you accept the current version shown at the time of submission.</p>
    ';
}
