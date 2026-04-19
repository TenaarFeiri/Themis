// Themis Titler Framework
// Receives chunked server payloads via HTTP-in, ACKs each chunk, reassembles,
// and lays out formatted text across 4 linked prim text panels.

integer MAX_TEXT_BYTES_PER_PRIM = 255;
list TITLER_LINKS = [2, 3, 4, 5];

vector gTextColor = <1.0, 1.0, 0.0>;
float gTextAlpha = 1.0;

key gUrlRequestId;
string gInboundUrl = "";

string gChunkMsgId = "";
string gChunkPayloadType = "";
integer gChunkTotal = 0;
list gChunkData = [];
string gCurrentMode = "normal";

integer utf8_bytes(string s)
{
    string b64 = llStringToBase64(s);
    integer len = llStringLength(b64);
    if (len == 0) {
        return 0;
    }

    integer pad = 0;
    if (len >= 2 && llGetSubString(b64, -2, -1) == "==") {
        pad = 2;
    } else if (llGetSubString(b64, -1, -1) == "=") {
        pad = 1;
    }

    return ((len * 3) / 4) - pad;
}

string fit_prefix_by_bytes(string s, integer maxBytes)
{
    if (maxBytes <= 0) {
        return "";
    }

    integer n = llStringLength(s);
    integer i;
    string out = "";
    for (i = 0; i < n; i += 1) {
        string ch = llGetSubString(s, i, i);
        string test = out + ch;
        if (utf8_bytes(test) > maxBytes) {
            return out;
        }
        out = test;
    }

    return out;
}

list empty_panels(integer count)
{
    integer i;
    list result = [];
    for (i = 0; i < count; i += 1) {
        result += [""];
    }
    return result;
}

list distribute_text_across_panels(string fullText, integer panelCount, integer byteLimit)
{
    list panels = empty_panels(panelCount);
    list lines = llParseStringKeepNulls(fullText, ["\n"], []);

    integer lineCount = llGetListLength(lines);
    integer panel = 0;
    integer i;

    for (i = 0; i < lineCount && panel < panelCount; i += 1) {
        string line = llList2String(lines, i);
        string segment = line;
        if (i < lineCount - 1) {
            segment += "\n";
        }

        while (segment != "" && panel < panelCount) {
            string current = llList2String(panels, panel);
            integer room = byteLimit - utf8_bytes(current);

            if (room <= 0) {
                panel += 1;
                continue;
            }

            string piece = fit_prefix_by_bytes(segment, room);
            if (piece == "") {
                panel += 1;
                continue;
            }

            panels = llListReplaceList(panels, [current + piece], panel, panel);
            integer usedChars = llStringLength(piece);
            segment = llDeleteSubString(segment, 0, usedChars - 1);

            if (segment != "") {
                panel += 1;
            }
        }
    }

    // If overflow remains, mark end of last panel so clipping is obvious.
    if (panel >= panelCount && i < lineCount) {
        integer last = panelCount - 1;
        string tail = llList2String(panels, last);
        string marker = "\n[...]";
        string trimmed = tail;

        while (trimmed != "" && utf8_bytes(trimmed + marker) > byteLimit) {
            trimmed = llDeleteSubString(trimmed, -1, -1);
        }

        panels = llListReplaceList(panels, [trimmed + marker], last, last);
    }

    return panels;
}

vector parse_rgb_normalized(string rgb)
{
    list parts = llParseStringKeepNulls(rgb, [","], []);
    if (llGetListLength(parts) != 3) {
        return gTextColor;
    }

    float r = (float)llList2String(parts, 0) / 255.0;
    float g = (float)llList2String(parts, 1) / 255.0;
    float b = (float)llList2String(parts, 2) / 255.0;

    return <r, g, b>;
}

string json_field(string json, string key)
{
    string value = llJsonGetValue(json, [key]);
    if (value == JSON_INVALID || value == JSON_NULL) {
        return "";
    }
    return value;
}

string build_titler_text_from_payload(string payloadJson)
{
    list kv = llJson2List(payloadJson);
    integer n = llGetListLength(kv);

    string title = "";
    list lines = [];

    integer i;
    for (i = 0; i + 1 < n; i += 2) {
        string key = llList2String(kv, i);
        string val = llList2String(kv, i + 1);

        if (key == "template") {
            // Metadata, not display text.
        } else if (key == "@invis@" || key == "0") {
            if (title == "") {
                title = val;
            }
        } else {
            if (key != "") {
                lines += [key + " " + val];
            }
        }
    }

    string body = llDumpList2String(lines, "\n");
    if (title != "" && body != "") {
        return title + "\n" + body;
    }
    if (title != "") {
        return title;
    }
    return body;
}

show_titler_text(string text)
{
    integer panelCount = llGetListLength(TITLER_LINKS);
    list panels = distribute_text_across_panels(text, panelCount, MAX_TEXT_BYTES_PER_PRIM);

    integer i;
    for (i = 0; i < panelCount; i += 1) {
        panels = llListReplaceList(
            panels,
            [fit_prefix_by_bytes(llList2String(panels, i), MAX_TEXT_BYTES_PER_PRIM)],
            i,
            i
        );
    }

    integer j;
    for (j = 0; j < panelCount; j += 1) {
        integer link = llList2Integer(TITLER_LINKS, j);
        string panelText = llList2String(panels, j);
        llSetLinkPrimitiveParamsFast(
            link,
            [PRIM_TEXT, panelText, gTextColor, gTextAlpha]
        );
    }
}

show_titler_panels(list panels)
{
    integer panelCount = llGetListLength(TITLER_LINKS);
    integer i;
    for (i = 0; i < panelCount; i += 1) {
        integer link = llList2Integer(TITLER_LINKS, i);
        string panelText = "";
        if (i < llGetListLength(panels)) {
            panelText = fit_prefix_by_bytes(llList2String(panels, i), MAX_TEXT_BYTES_PER_PRIM);
        }
        llSetLinkPrimitiveParamsFast(
            link,
            [PRIM_TEXT, panelText, gTextColor, gTextAlpha]
        );
    }
}

list extract_server_panels(string payloadJson)
{
    list out = [];
    integer i;
    integer panelCount = llGetListLength(TITLER_LINKS);
    for (i = 0; i < panelCount; i += 1) {
        string panel = llJsonGetValue(payloadJson, ["layout", "panels", (string)i]);
        if (panel == JSON_INVALID || panel == JSON_NULL) {
            return [];
        }
        out += [panel];
    }
    return out;
}

apply_server_style(string payloadJson)
{
    string color = llJsonGetValue(payloadJson, ["style", "color"]);
    if (color == JSON_INVALID || color == JSON_NULL || color == "") {
        color = json_field(payloadJson, "color");
    }

    string opacity = llJsonGetValue(payloadJson, ["style", "opacity"]);
    if (opacity == JSON_INVALID || opacity == JSON_NULL || opacity == "") {
        opacity = json_field(payloadJson, "opacity");
    }

    if (color != "") {
        gTextColor = parse_rgb_normalized(color);
    }
    if (opacity != "") {
        float op = (float)opacity;
        if (op < 0.0) op = 0.0;
        if (op > 1.0) op = 1.0;
        gTextAlpha = op;
    }
}

apply_options_payload(string payloadJson)
{
    string color = json_field(payloadJson, "color");
    string opacity = json_field(payloadJson, "opacity");

    if (color != "") {
        gTextColor = parse_rgb_normalized(color);
    }
    if (opacity != "") {
        float op = (float)opacity;
        if (op < 0.0) op = 0.0;
        if (op > 1.0) op = 1.0;
        gTextAlpha = op;
    }
}

string ack_json(string msgId, integer index)
{
    // Keep this minimal and deterministic for server parser.
    return llList2Json(
        JSON_OBJECT,
        ["t", "ack", "id", msgId, "i", (string)index, "ok", "1"]
    );
}

clear_chunk_state()
{
    gChunkMsgId = "";
    gChunkPayloadType = "";
    gChunkTotal = 0;
    gChunkData = [];
}

string join_list(list parts)
{
    return llDumpList2String(parts, "");
}

handle_reassembled_payload(string payloadType, string payloadJson)
{
    if (payloadType == "titler" || payloadType == "generic") {
        string mode = json_field(payloadJson, "mode");
        if (mode != "") {
            gCurrentMode = mode;
        }

        apply_server_style(payloadJson);

        // Server-authoritative preferred path: layout.panels precomputed by backend.
        list serverPanels = extract_server_panels(payloadJson);
        if (llGetListLength(serverPanels) > 0) {
            show_titler_panels(serverPanels);
            return;
        }

        // Fallback path for legacy payloads where the server still sends raw titler JSON.
        string text = build_titler_text_from_payload(payloadJson);
        show_titler_text(text);
        return;
    }

    if (payloadType == "options") {
        apply_options_payload(payloadJson);
        return;
    }

    if (payloadType == "stats") {
        // Optionally map stats to a compact display in a future pass.
        return;
    }
}

handle_chunk_request(key reqId, string body)
{
    string t = json_field(body, "t");
    if (t != "chunk") {
        llHTTPResponse(reqId, 400, "ERR unsupported_type");
        return;
    }

    string msgId = json_field(body, "id");
    string payloadType = json_field(body, "pt");
    integer idx = (integer)json_field(body, "i");
    integer total = (integer)json_field(body, "n");
    string enc = json_field(body, "enc");
    string data = json_field(body, "d");

    if (msgId == "" || idx < 1 || total < 1 || data == "") {
        llHTTPResponse(reqId, 400, "ERR invalid_chunk_fields");
        return;
    }
    if (enc != "b64") {
        llHTTPResponse(reqId, 400, "ERR unsupported_encoding");
        return;
    }

    // Reset receiver state when a new message stream starts.
    if (gChunkMsgId == "" || msgId != gChunkMsgId || idx == 1) {
        gChunkMsgId = msgId;
        gChunkPayloadType = payloadType;
        gChunkTotal = total;
        gChunkData = [];
    }

    if (msgId != gChunkMsgId || total != gChunkTotal) {
        llHTTPResponse(reqId, 409, "ERR chunk_stream_mismatch");
        return;
    }

    integer expectedIndex = llGetListLength(gChunkData) + 1;
    if (idx != expectedIndex) {
        llHTTPResponse(reqId, 409, "ERR out_of_order_chunk");
        return;
    }

    string decoded = llBase64ToString(data);
    gChunkData += [decoded];

    llHTTPResponse(reqId, 200, ack_json(msgId, idx));

    if (idx == total) {
        string payloadJson = join_list(gChunkData);
        handle_reassembled_payload(gChunkPayloadType, payloadJson);
        clear_chunk_state();
    }
}

request_http_url()
{
    if (gUrlRequestId) {
        llReleaseURL(gInboundUrl);
    }
    gInboundUrl = "";
    gUrlRequestId = llRequestURL();
}

default
{
    state_entry()
    {
        llOwnerSay("Themis Titler Framework: requesting HTTP-in URL...");
        request_http_url();
    }

    on_rez(integer start_param)
    {
        llResetScript();
    }

    changed(integer change)
    {
        if (change & CHANGED_OWNER) {
            llResetScript();
        }
    }

    http_request(key id, string method, string body)
    {
        if (method == URL_REQUEST_GRANTED) {
            gInboundUrl = body;
            llOwnerSay("Themis Titler URL: " + gInboundUrl);
            return;
        }

        if (method == URL_REQUEST_DENIED) {
            llOwnerSay("Themis Titler URL denied: " + body);
            return;
        }

        if (method == "GET") {
            llHTTPResponse(id, 200, "OK titler");
            return;
        }

        if (method == "POST") {
            handle_chunk_request(id, body);
            return;
        }

        llHTTPResponse(id, 405, "ERR method_not_allowed");
    }
}
