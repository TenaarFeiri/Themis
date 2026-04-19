// Themis HUD Framework
// Receives chunked server payloads via HTTP-in, ACKs each chunk, reassembles,
// and updates media prim URL/settings for WebHUD use.

integer HUD_LINK = LINK_THIS;
integer HUD_FACE = 0;
integer HUD_AUTO_PLAY = TRUE;
integer HUD_LOOP = FALSE;

key gUrlRequestId;
string gInboundUrl = "";

string gChunkMsgId = "";
string gChunkPayloadType = "";
integer gChunkTotal = 0;
list gChunkData = [];

string json_field(string json, string key)
{
    string value = llJsonGetValue(json, [key]);
    if (value == JSON_INVALID || value == JSON_NULL) {
        return "";
    }
    return value;
}

string ack_json(string msgId, integer index)
{
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

set_media_url(string url)
{
    if (url == "") {
        return;
    }

    llSetLinkMedia(
        HUD_LINK,
        HUD_FACE,
        [
            PRIM_MEDIA_CURRENT_URL, url,
            PRIM_MEDIA_HOME_URL, url,
            PRIM_MEDIA_AUTO_PLAY, HUD_AUTO_PLAY,
            PRIM_MEDIA_AUTO_LOOP, HUD_LOOP,
            PRIM_MEDIA_CONTROLS, PRIM_MEDIA_CONTROLS_MINI,
            PRIM_MEDIA_PERMS_CONTROL, PRIM_MEDIA_PERM_OWNER,
            PRIM_MEDIA_PERMS_INTERACT, PRIM_MEDIA_PERM_OWNER
        ]
    );
}

apply_hud_payload(string payloadJson)
{
    string webUrl = json_field(payloadJson, "webhud_url");
    if (webUrl == "") {
        webUrl = json_field(payloadJson, "url");
    }

    string face = json_field(payloadJson, "face");
    if (face != "") {
        HUD_FACE = (integer)face;
    }

    string link = json_field(payloadJson, "link");
    if (link != "") {
        HUD_LINK = (integer)link;
    }

    string autoPlay = json_field(payloadJson, "auto_play");
    if (autoPlay != "") {
        HUD_AUTO_PLAY = (integer)autoPlay;
    }

    string loop = json_field(payloadJson, "loop");
    if (loop != "") {
        HUD_LOOP = (integer)loop;
    }

    if (webUrl != "") {
        set_media_url(webUrl);
    }
}

handle_reassembled_payload(string payloadType, string payloadJson)
{
    if (payloadType == "hud" || payloadType == "options" || payloadType == "generic") {
        apply_hud_payload(payloadJson);
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
        llOwnerSay("Themis HUD Framework: requesting HTTP-in URL...");
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
            llOwnerSay("Themis HUD URL: " + gInboundUrl);
            return;
        }

        if (method == URL_REQUEST_DENIED) {
            llOwnerSay("Themis HUD URL denied: " + body);
            return;
        }

        if (method == "GET") {
            llHTTPResponse(id, 200, "OK hud");
            return;
        }

        if (method == "POST") {
            handle_chunk_request(id, body);
            return;
        }

        llHTTPResponse(id, 405, "ERR method_not_allowed");
    }
}
