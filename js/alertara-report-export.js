/**
 * Shared Alertara QC branded DOCX export.
 * Layout: centered brand/address header, title, fields, watermark, Generated on.
 * Requires JSZip (global).
 */
(function (global) {
    'use strict';

    var BRAND_NAME = 'ALERTARA QC';
    var BRAND_ADDRESS = 'Patnubay Street, Barangay San Agustin, Novaliches Quezon City, District 5, Quezon City';
    var BRAND_LOCATION = 'Barangay San Agustin, Quezon City';
    var WATERMARK_URL = 'images/alertara-export-watermark.png';
    var BRAND_COLOR = '14532D'; // dark green
    var NL = '\n';

    var watermarkCache = null;

    function escapeXml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    }

    function fieldXml(label, value) {
        return (
            '        <w:p>' + NL +
            '            <w:pPr><w:spacing w:after="60" w:line="276" w:lineRule="auto"/></w:pPr>' + NL +
            '            <w:r><w:rPr><w:b/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr><w:t>' + escapeXml(label) + ':</w:t></w:r>' + NL +
            '            <w:r><w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr><w:t xml:space="preserve"> ' + escapeXml(value == null || value === '' ? '—' : value) + '</w:t></w:r>' + NL +
            '        </w:p>' + NL
        );
    }

    function blockXml(label, value) {
        return (
            '        <w:p>' + NL +
            '            <w:pPr><w:spacing w:before="360" w:after="120"/></w:pPr>' + NL +
            '            <w:r><w:rPr><w:b/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr><w:t>' + escapeXml(label) + ':</w:t></w:r>' + NL +
            '        </w:p>' + NL +
            '        <w:p>' + NL +
            '            <w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr>' + NL +
            '            <w:r><w:rPr><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr><w:t>' + escapeXml(value == null || value === '' ? '—' : value) + '</w:t></w:r>' + NL +
            '        </w:p>' + NL
        );
    }

    function centeredPara(text, opts) {
        opts = opts || {};
        var size = opts.size || 22;
        var bold = opts.bold ? '<w:b/>' : '';
        var color = opts.color ? '<w:color w:val="' + opts.color + '"/>' : '';
        var after = opts.after != null ? opts.after : 120;
        var before = opts.before != null ? opts.before : 0;
        return (
            '        <w:p>' + NL +
            '            <w:pPr>' + NL +
            '                <w:jc w:val="center"/>' + NL +
            '                <w:spacing w:before="' + before + '" w:after="' + after + '"/>' + NL +
            '            </w:pPr>' + NL +
            '            <w:r>' + NL +
            '                <w:rPr>' + bold + color + '<w:sz w:val="' + size + '"/><w:szCs w:val="' + size + '"/></w:rPr>' + NL +
            '                <w:t>' + escapeXml(text) + '</w:t>' + NL +
            '            </w:r>' + NL +
            '        </w:p>' + NL
        );
    }

    function separatorLine() {
        return (
            '        <w:p>' + NL +
            '            <w:pPr>' + NL +
            '                <w:pBdr>' + NL +
            '                    <w:bottom w:val="single" w:sz="8" w:space="1" w:color="A0A0A0"/>' + NL +
            '                </w:pBdr>' + NL +
            '                <w:spacing w:before="80" w:after="280"/>' + NL +
            '            </w:pPr>' + NL +
            '            <w:r><w:t></w:t></w:r>' + NL +
            '        </w:p>' + NL
        );
    }

    function pictureDrawing(rId, name, cx, cy, docPrId, behindDoc) {
        var wrap = behindDoc
            ? (
                '                    <wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="0" behindDoc="1" locked="0" layoutInCell="1" allowOverlap="1">' + NL +
                '                        <wp:simplePos x="0" y="0"/>' + NL +
                '                        <wp:positionH relativeFrom="page"><wp:align>center</wp:align></wp:positionH>' + NL +
                '                        <wp:positionV relativeFrom="page"><wp:align>center</wp:align></wp:positionV>' + NL +
                '                        <wp:extent cx="' + cx + '" cy="' + cy + '"/>' + NL +
                '                        <wp:effectExtent l="0" t="0" r="0" b="0"/>' + NL +
                '                        <wp:wrapNone/>' + NL +
                '                        <wp:docPr id="' + docPrId + '" name="' + escapeXml(name) + '"/>' + NL +
                '                        <a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">' + NL +
                '                            <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">' + NL +
                '                                <pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">' + NL +
                '                                    <pic:nvPicPr><pic:cNvPr id="0" name="' + escapeXml(name) + '"/><pic:cNvPicPr/></pic:nvPicPr>' + NL +
                '                                    <pic:blipFill><a:blip r:embed="' + rId + '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>' + NL +
                '                                    <pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' + cx + '" cy="' + cy + '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>' + NL +
                '                                </pic:pic>' + NL +
                '                            </a:graphicData>' + NL +
                '                        </a:graphic>' + NL +
                '                    </wp:anchor>'
            )
            : (
                '                    <wp:inline distT="0" distB="0" distL="0" distR="0">' + NL +
                '                        <wp:extent cx="' + cx + '" cy="' + cy + '"/>' + NL +
                '                        <wp:docPr id="' + docPrId + '" name="' + escapeXml(name) + '"/>' + NL +
                '                        <a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">' + NL +
                '                            <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">' + NL +
                '                                <pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">' + NL +
                '                                    <pic:nvPicPr><pic:cNvPr id="0" name="' + escapeXml(name) + '"/><pic:cNvPicPr/></pic:nvPicPr>' + NL +
                '                                    <pic:blipFill><a:blip r:embed="' + rId + '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>' + NL +
                '                                    <pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' + cx + '" cy="' + cy + '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>' + NL +
                '                                </pic:pic>' + NL +
                '                            </a:graphicData>' + NL +
                '                        </a:graphic>' + NL +
                '                    </wp:inline>'
            );

        return (
            '        <w:p>' + NL +
            '            <w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr>' + NL +
            '            <w:r>' + NL +
            '                <w:drawing>' + NL +
            wrap + NL +
            '                </w:drawing>' + NL +
            '            </w:r>' + NL +
            '        </w:p>' + NL
        );
    }

    function inlineImageXml(rId, name, cx, cy) {
        return pictureDrawing(rId, name, cx, cy, Math.floor(Math.random() * 9000 + 100), false);
    }

    function bytesFromBase64(b64) {
        var binary = atob(b64);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes;
    }

    async function fetchAsBytes(url) {
        var response = await fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'v=' + Date.now());
        if (!response.ok) throw new Error('Failed to load ' + url);
        var buffer = await response.arrayBuffer();
        return new Uint8Array(buffer);
    }

    async function loadWatermarkPart() {
        if (watermarkCache) return watermarkCache;
        try {
            var bytes = await fetchAsBytes(WATERMARK_URL);
            watermarkCache = {
                bytes: bytes,
                ext: 'png',
                mime: 'image/png',
                // ~4.2 inch watermark
                cx: 3840480,
                cy: 3840480
            };
        } catch (e) {
            console.warn('Alertara export watermark unavailable:', e);
            watermarkCache = null;
        }
        return watermarkCache;
    }

    async function decodeDataImage(src) {
        if (!src || String(src).indexOf('data:image/') !== 0) return null;
        var match = String(src).match(/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/i);
        if (!match) return null;
        var mime = match[1].toLowerCase();
        var bytes = bytesFromBase64(match[2]);
        var ext = 'jpg';
        if (mime.indexOf('png') !== -1) ext = 'png';
        else if (mime.indexOf('gif') !== -1) ext = 'gif';
        else if (mime.indexOf('webp') !== -1) ext = 'webp';

        var widthPx = 800;
        var heightPx = 600;
        try {
            var dims = await new Promise(function (resolve, reject) {
                var img = new Image();
                img.onload = function () {
                    resolve({
                        width: img.naturalWidth || 800,
                        height: img.naturalHeight || 600
                    });
                };
                img.onerror = reject;
                img.src = src;
            });
            widthPx = dims.width;
            heightPx = dims.height;
        } catch (e) { /* defaults */ }

        var maxWidthEmu = 5486400;
        var ratio = heightPx / Math.max(widthPx, 1);
        return {
            bytes: bytes,
            ext: ext,
            mime: mime,
            cx: maxWidthEmu,
            cy: Math.max(914400, Math.round(maxWidthEmu * ratio))
        };
    }

    function buildHeaderXml() {
        return (
            centeredPara(BRAND_NAME, { bold: true, size: 40, after: 60, color: BRAND_COLOR }) +
            centeredPara(BRAND_ADDRESS, { size: 24, after: 80, color: '555555' }) + // 12pt
            separatorLine()
        );
    }

    function sectionBodyXml(section) {
        var xml = '';
        if (section.fields && section.fields.length) {
            section.fields.forEach(function (f) {
                xml += fieldXml(f.label, f.value);
            });
        }
        if (section.blocks && section.blocks.length) {
            section.blocks.forEach(function (b) {
                xml += blockXml(b.label, b.value);
            });
        }
        if (section._imageXml) {
            xml += section._imageXml;
        }
        return { xml: xml };
    }

    async function prepareSectionImages(section, startRelNum) {
        var imageXml = '';
        var mediaFiles = [];
        var rels = [];
        var n = startRelNum;
        var images = section.images || [];

        for (var i = 0; i < images.length; i++) {
            var img = images[i];
            var label = img.label || 'Attachment';
            var part = await decodeDataImage(img.src);
            imageXml +=
                '        <w:p>' + NL +
                '            <w:pPr><w:spacing w:before="280"/></w:pPr>' + NL +
                '            <w:r><w:rPr><w:b/></w:rPr><w:t>' + escapeXml(label) + ':</w:t></w:r>' + NL +
                '        </w:p>' + NL;
            if (!part) {
                imageXml += '        <w:p><w:r><w:t>No photo uploaded</w:t></w:r></w:p>' + NL;
                continue;
            }
            var rId = 'rIdImg' + n;
            var fileName = 'image' + n + '.' + part.ext;
            n += 1;
            mediaFiles.push({ path: 'word/media/' + fileName, bytes: part.bytes, ext: part.ext, mime: part.mime });
            rels.push({ id: rId, target: 'media/' + fileName });
            imageXml += inlineImageXml(rId, fileName, part.cx, part.cy);
        }

        section._imageXml = imageXml;
        return { nextRelNum: n, mediaFiles: mediaFiles, rels: rels };
    }

    function downloadBlob(blob, fileName) {
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    async function downloadReport(options) {
        if (typeof JSZip === 'undefined') {
            throw new Error('Export library not loaded. Please refresh the page.');
        }
        options = options || {};
        var title = options.title || 'REPORT';
        var subtitle = options.subtitle != null ? options.subtitle : BRAND_LOCATION;
        var fileName = options.fileName || ('alertara_report_' + Date.now() + '.docx');

        var sections = options.sections;
        if (!sections || !sections.length) {
            sections = [{
                fields: options.fields || [],
                blocks: options.blocks || [],
                images: options.images || []
            }];
        }

        var zip = new JSZip();
        var watermark = await loadWatermarkPart();
        var mediaFiles = [];
        var imageRels = [];
        var contentTypeDefaults = {};
        var relNum = 20;

        if (watermark) {
            mediaFiles.push({ path: 'word/media/watermark.png', bytes: watermark.bytes, ext: 'png', mime: 'image/png' });
            imageRels.push({ id: 'rIdWatermark', target: 'media/watermark.png' });
            contentTypeDefaults.png = 'image/png';
        }

        for (var s = 0; s < sections.length; s++) {
            var prepared = await prepareSectionImages(sections[s], relNum);
            relNum = prepared.nextRelNum;
            prepared.mediaFiles.forEach(function (mf) {
                mediaFiles.push(mf);
                contentTypeDefaults[mf.ext] = mf.mime;
            });
            prepared.rels.forEach(function (r) {
                imageRels.push(r);
            });
        }

        var body = '';
        if (watermark) {
            body += pictureDrawing('rIdWatermark', 'Watermark', watermark.cx, watermark.cy, 99, true);
        }
        body += buildHeaderXml();
        body += centeredPara(title, { bold: true, size: 36, after: 80, before: 80, color: '000000' });
        body += centeredPara(subtitle, { size: 20, after: 360, color: '333333' });

        for (var i = 0; i < sections.length; i++) {
            if (i > 0) {
                body +=
                    '        <w:p>' + NL +
                    '            <w:pPr><w:spacing w:before="400" w:after="200"/></w:pPr>' + NL +
                    '            <w:r><w:t>---</w:t></w:r>' + NL +
                    '        </w:p>' + NL;
            }
            if (sections[i].heading) {
                body += centeredPara(sections[i].heading, { bold: true, size: 24, after: 200 });
            }
            body += sectionBodyXml(sections[i]).xml;
        }

        body +=
            '        <w:p>' + NL +
            '            <w:pPr><w:jc w:val="right"/><w:spacing w:before="720"/></w:pPr>' + NL +
            '            <w:r><w:rPr><w:b/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr><w:t>Generated on: ' + escapeXml(new Date().toLocaleString()) + '</w:t></w:r>' + NL +
            '        </w:p>' + NL;

        var xmlDecl = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' + NL;

        var contentTypes =
            xmlDecl +
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' + NL +
            '    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' + NL +
            '    <Default Extension="xml" ContentType="application/xml"/>' + NL;
        Object.keys(contentTypeDefaults).forEach(function (ext) {
            contentTypes += '    <Default Extension="' + ext + '" ContentType="' + contentTypeDefaults[ext] + '"/>' + NL;
        });
        contentTypes +=
            '    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' + NL +
            '    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>' + NL +
            '</Types>';

        var documentXml =
            xmlDecl +
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"' + NL +
            '            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"' + NL +
            '            xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">' + NL +
            '    <w:body>' + NL +
            body +
            '        <w:sectPr>' + NL +
            '            <w:pgSz w:w="12240" w:h="15840"/>' + NL +
            '            <w:pgMar w:top="720" w:right="864" w:bottom="720" w:left="864"/>' + NL +
            '        </w:sectPr>' + NL +
            '    </w:body>' + NL +
            '</w:document>';

        var stylesXml =
            xmlDecl +
            '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' + NL +
            '    <w:docDefaults>' + NL +
            '        <w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:rPrDefault>' + NL +
            '    </w:docDefaults>' + NL +
            '    <w:style w:type="paragraph" w:styleId="Normal" w:default="1">' + NL +
            '        <w:name w:val="Normal"/><w:qFormat/>' + NL +
            '    </w:style>' + NL +
            '</w:styles>';

        var rels =
            xmlDecl +
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' + NL +
            '    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>' + NL +
            '</Relationships>';

        var wordRels =
            xmlDecl +
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' + NL +
            '    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' + NL;
        imageRels.forEach(function (r) {
            wordRels +=
                '    <Relationship Id="' + r.id + '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="' + r.target + '"/>' + NL;
        });
        wordRels += '</Relationships>';

        zip.file('[Content_Types].xml', contentTypes);
        zip.file('word/document.xml', documentXml);
        zip.file('word/styles.xml', stylesXml);
        zip.file('_rels/.rels', rels);
        zip.file('word/_rels/document.xml.rels', wordRels);
        mediaFiles.forEach(function (mf) {
            zip.file(mf.path, mf.bytes);
        });

        var blob = await zip.generateAsync({
            type: 'blob',
            mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        });
        downloadBlob(blob, fileName);
        return fileName;
    }

    global.AlertaraReportExport = {
        escapeXml: escapeXml,
        fieldXml: fieldXml,
        blockXml: blockXml,
        downloadReport: downloadReport,
        BRAND_LOCATION: BRAND_LOCATION
    };
})(typeof window !== 'undefined' ? window : this);
