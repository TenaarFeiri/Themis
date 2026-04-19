<?php
declare(strict_types=1);

namespace Themis\Content;

require_once __DIR__ . '/../Autoloader.php';

header('Content-Type: text/html; charset=utf-8');

$out = '';
$out .= '<link rel="stylesheet" href="/themis/content/css/character_menu.css?v=1">';
$out .= '<style>';
$out .= '.themis-combat-singleview{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:12px;}';
$out .= '.themis-combat-singleview .themis-combat-block[data-key="turn"]{grid-column:2;grid-row:1 / span 3;position:sticky;top:8px;align-self:start;}';
$out .= '.themis-combat-singleview .themis-combat-block[data-key="overview"]{grid-column:1;grid-row:1;}';
$out .= '.themis-combat-singleview .themis-combat-block[data-key="targets"]{grid-column:1;grid-row:2;}';
$out .= '.themis-combat-singleview .themis-combat-block[data-key="instance"]{grid-column:1;grid-row:3;}';
$out .= '.themis-combat-singleview .themis-character-menu{display:none!important;}';
$out .= '.themis-energy-wrap{display:flex;align-items:center;gap:8px;}';
$out .= '.themis-energy-track{position:relative;width:26px;height:150px;border:1px solid #8c7a49;border-radius:4px;background:linear-gradient(180deg,#f4ecdb 0%,#e7dcc2 100%);overflow:hidden;}';
$out .= '.themis-energy-fill{position:absolute;left:0;right:0;bottom:0;height:0%;background:linear-gradient(180deg,#5a9f48 0%,#2f7a36 100%);}';
$out .= '.themis-energy-slider{position:absolute;inset:0;opacity:0.86;margin:0;width:26px;height:150px;writing-mode:vertical-lr;direction:rtl;appearance:slider-vertical;-webkit-appearance:slider-vertical;background:transparent;}';
$out .= '.themis-vitals-row{padding:4px 0;border-bottom:1px dashed #dfd2b3;}';
$out .= '.themis-vitals-bar{position:relative;height:8px;border-radius:999px;background:#e7dcc2;overflow:hidden;margin-top:4px;}';
$out .= '.themis-vitals-bar > span{position:absolute;left:0;top:0;bottom:0;}';
$out .= '.themis-vitals-hp{background:#b13a2c;}';
$out .= '.themis-vitals-energy{background:#3f8c45;}';
$out .= '@media (max-width: 900px){.themis-combat-singleview{grid-template-columns:1fr;}.themis-combat-singleview .themis-combat-block[data-key="turn"]{grid-column:1;grid-row:auto;position:static;}}';
$out .= '</style>';
$out .= '<div class="themis-content-container themis-character-hud">';
$out .= '<div class="themis-character-panel">';
$out .= '<nav class="themis-character-menu themis-fragment-menu" aria-label="Combat menu">';
$out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="overview" onclick="ThemisHUD.toggleVisibility(\'themis-combat-block\', \'overview\'); return false;">Overview</a>';
$out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="targets" onclick="ThemisHUD.toggleVisibility(\'themis-combat-block\', \'targets\'); return false;">Targets</a>';
$out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="instance" onclick="ThemisHUD.toggleVisibility(\'themis-combat-block\', \'instance\'); return false;">Instance</a>';
$out .= '<a href="#" role="button" class="themis-character-menu-item" data-key="turn" onclick="ThemisHUD.toggleVisibility(\'themis-combat-block\', \'turn\'); return false;">Turn</a>';
$out .= '</nav>';

$out .= '<section class="themis-character-content themis-combat-singleview" id="themis-combat-content">';
$out .= '<div class="themis-combat-block" data-key="overview">';
$out .= '<h3>Combat Scaffold</h3>';
$out .= '<p>Range-aware duel instance scaffold with timed turn rounds and server-authoritative resolution.</p>';
$out .= '<p id="combat_test_actor_badge" style="display:none;font-size:0.9rem;font-weight:600;color:#274c77;"></p>';
$out .= '<div id="combat_invite_prompt" style="display:none;padding:8px;border:1px solid #c8b68a;border-radius:6px;background:#f8f1dd;margin-bottom:8px;">';
$out .= '<div style="font-weight:700;margin-bottom:6px;">Pending Challenge</div>';
$out .= '<div id="combat_invite_text" style="margin-bottom:8px;"></div>';
$out .= '<div class="themis-form-actions">';
$out .= '<button type="button" class="themis-form-button" id="combat_invite_accept">Accept</button>';
$out .= '<button type="button" class="themis-form-button" id="combat_invite_decline">Decline</button>';
$out .= '</div>';
$out .= '</div>';
$out .= '<div class="themis-form-actions">';
$out .= '<button type="button" class="themis-form-button" id="combat_sync_radar">Sync Radar</button>';
$out .= '<button type="button" class="themis-form-button" id="combat_tick">Tick Resolver</button>';
$out .= '<button type="button" class="themis-form-button" id="combat_refresh_state">Refresh State</button>';
$out .= '</div>';
$out .= '<div id="combat_vitals_overview" style="margin-bottom:8px;padding:8px;border:1px solid #d5c59d;border-radius:6px;background:#fefaf0;"><em>No participant vitals available.</em></div>';
$out .= '<div id="combat_log" style="margin-bottom:8px;padding:8px;border:1px solid #d5c59d;border-radius:6px;background:#fff8e9;max-height:180px;overflow:auto;"></div>';
$out .= '<pre id="combat_overview_output" style="white-space:pre-wrap;max-height:220px;overflow:auto;"></pre>';
$out .= '</div>';

$out .= '<div class="themis-combat-block" data-key="targets">';
$out .= '<h3>Challenge Targets (Chat Range)</h3>';
$out .= '<p>Targets are resolved from your radar sync list and active character mapping.</p>';
$out .= '<div class="themis-form-actions">';
$out .= '<button type="button" class="themis-form-button" id="combat_load_targets">Load Targets</button>';
$out .= '</div>';
$out .= '<pre id="combat_targets_output" style="white-space:pre-wrap;max-height:220px;overflow:auto;"></pre>';
$out .= '<label for="combat_target_select">Target Character</label>';
$out .= '<select id="combat_target_select" class="themis-form-input"><option value="">Select target...</option></select>';
$out .= '<label for="combat_target_uuid">Target Player UUID</label>';
$out .= '<input id="combat_target_uuid" class="themis-form-input" type="text" placeholder="uuid">';
$out .= '<div class="themis-form-actions">';
$out .= '<button type="button" class="themis-form-button" id="combat_challenge">Challenge</button>';
$out .= '<button type="button" class="themis-form-button" id="combat_join_host">Join Nearby Host</button>';
$out .= '</div>';
$out .= '</div>';

$out .= '<div class="themis-combat-block" data-key="instance">';
$out .= '<h3>Instance State</h3>';
$out .= '<label for="combat_instance_id">Instance ID</label>';
$out .= '<input id="combat_instance_id" class="themis-form-input" type="number" min="1" placeholder="auto from latest">';
$out .= '<div class="themis-form-actions">';
$out .= '<button type="button" class="themis-form-button" id="combat_load_instance">Load Instance</button>';
$out .= '</div>';
$out .= '<div id="combat_vitals" style="margin-bottom:8px;padding:8px;border:1px solid #d5c59d;border-radius:6px;background:#fefaf0;"><em>No participant vitals available.</em></div>';
$out .= '<pre id="combat_instance_output" style="white-space:pre-wrap;max-height:280px;overflow:auto;"></pre>';
$out .= '</div>';

$out .= '<div class="themis-combat-block" data-key="turn">';
$out .= '<h3>Submit Turn Action</h3>';
$out .= '<div style="font-weight:700;margin-bottom:6px;">Action</div>';
$out .= '<div id="combat_action_buttons" class="themis-form-actions">';
$out .= '<button type="button" class="themis-form-button combat-action-btn" data-action="attack">Attack</button>';
$out .= '<button type="button" class="themis-form-button combat-action-btn" data-action="defend">Defend</button>';
$out .= '<button type="button" class="themis-form-button combat-action-btn" data-action="feint">Feint</button>';
$out .= '<button type="button" class="themis-form-button combat-action-btn" data-action="spell">Spell</button>';
$out .= '<button type="button" class="themis-form-button combat-action-btn" data-action="wait">Wait</button>';
$out .= '<button type="button" class="themis-form-button combat-action-btn" data-action="forfeit">Forfeit</button>';
$out .= '</div>';
$out .= '<div id="combat_selected_action" style="font-weight:600;margin:6px 0 8px;">Selected: attack</div>';
$out .= '<label for="combat_action_target_uuid">Target UUID (optional for defend/wait)</label>';
$out .= '<input id="combat_action_target_uuid" class="themis-form-input" type="text" placeholder="target uuid">';
$out .= '<label for="combat_action_target_select">Action Target Character</label>';
$out .= '<select id="combat_action_target_select" class="themis-form-input"><option value="">Use target UUID/manual...</option></select>';
$out .= '<div style="display:flex;gap:12px;align-items:flex-start;margin:8px 0;">';
$out .= '<div style="flex:1;">';
$out .= '<label for="combat_attack_kind">Attack Kind</label>';
$out .= '<select id="combat_attack_kind" class="themis-form-input">';
$out .= '<option value="physical">Physical</option>';
$out .= '<option value="magical">Magical</option>';
$out .= '</select>';
$out .= '</div>';
$out .= '<div class="themis-energy-wrap">';
$out .= '<label for="combat_attack_power" style="margin:0;">Power</label>';
$out .= '<div class="themis-energy-track" id="combat_energy_track"><div class="themis-energy-fill" id="combat_energy_fill"></div><input id="combat_attack_power" class="themis-energy-slider" type="range" min="0" max="12" step="1" value="6"></div>';
$out .= '<div id="combat_attack_power_value" style="font-weight:700;min-width:22px;text-align:center;">6</div>';
$out .= '<div id="combat_energy_text" style="font-size:0.82rem;min-width:90px;">Energy: -/-</div>';
$out .= '</div>';
$out .= '</div>';
$out .= '<label for="combat_action_payload">Payload JSON (optional)</label>';
$out .= '<textarea id="combat_action_payload" class="themis-profile-textarea" rows="4" placeholder="{\"stance\":\"aggressive\"}"></textarea>';
$out .= '<div class="themis-form-actions">';
$out .= '<button type="button" class="themis-form-button" id="combat_submit_action">Submit Action</button>';
$out .= '</div>';
$out .= '<pre id="combat_turn_output" style="white-space:pre-wrap;max-height:220px;overflow:auto;"></pre>';
$out .= '</div>';

$out .= '</section>';
$out .= '</div>';
$out .= '</div>';

$out .= <<<'HTML'
<script>
(function(){
var root=document.getElementById("themis-combat-content"); if(!root||root.dataset.bound==="1"){return;} root.dataset.bound="1";
var outputOverview=document.getElementById("combat_overview_output");
var outputTargets=document.getElementById("combat_targets_output");
var outputInstance=document.getElementById("combat_instance_output");
var outputTurn=document.getElementById("combat_turn_output");
var inputTarget=document.getElementById("combat_target_uuid");
var inputTargetSelect=document.getElementById("combat_target_select");
var inputInstance=document.getElementById("combat_instance_id");
var actionButtons=Array.from(document.querySelectorAll("#combat_action_buttons .combat-action-btn"));
var selectedActionLabel=document.getElementById("combat_selected_action");
var inputActionTarget=document.getElementById("combat_action_target_uuid");
var inputActionTargetSelect=document.getElementById("combat_action_target_select");
var inputAttackKind=document.getElementById("combat_attack_kind");
var inputAttackPower=document.getElementById("combat_attack_power");
var outputAttackPower=document.getElementById("combat_attack_power_value");
var inputPayload=document.getElementById("combat_action_payload");
var testBadge=document.getElementById("combat_test_actor_badge");
var invitePrompt=document.getElementById("combat_invite_prompt");
var inviteText=document.getElementById("combat_invite_text");
var vitalsBox=document.getElementById("combat_vitals");
var vitalsBoxOverview=document.getElementById("combat_vitals_overview");
var combatLog=document.getElementById("combat_log");
var energyFill=document.getElementById("combat_energy_fill");
var energyText=document.getElementById("combat_energy_text");
var cachedInstanceId=null;
var latestTargets=[];
var pendingInvites=[];
var selectedAction="attack";
var latestInstance=null;
var pageParams=new URLSearchParams(window.location.search||"");
var testMode=(pageParams.get("test_mode")||"")==="1";
var testActor=(pageParams.get("test_actor_uuid")||"").trim();
var peerActor=(pageParams.get("peer_actor_uuid")||"").trim();
var realtime=(window.ThemisRealtime&&window.ThemisRealtime.enabled)?window.ThemisRealtime:null;
var socket=null;
if(testMode&&testActor&&testBadge){ testBadge.style.display="block"; testBadge.textContent="Testing as actor: "+testActor; }

function setOut(el,obj){ if(!el){return;} if(typeof obj==="string"){el.textContent=obj;return;} el.textContent=JSON.stringify(obj,null,2); }
function updatePowerLabel(){ if(outputAttackPower&&inputAttackPower){ outputAttackPower.textContent=String(inputAttackPower.value||"0"); } }
function fillTargetSelects(targets){ latestTargets=Array.isArray(targets)?targets:[]; var html="<option value=\"\">Select target...</option>"; latestTargets.forEach(function(t){ var u=(t.player_uuid||""); var n=(t.character_name||u||"unknown"); html += "<option value=\""+u+"\">"+n+" ("+u+")</option>"; }); if(inputTargetSelect){ inputTargetSelect.innerHTML=html; } if(inputActionTargetSelect){ inputActionTargetSelect.innerHTML=html.replace("Select target...","Use target UUID/manual..."); } }
function withTestParams(obj){ var merged=Object.assign({}, obj||{}); if(testMode){ merged.test_mode="1"; if(testActor){ merged.test_actor_uuid=testActor; } } return merged; }
function isUuid(v){ return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test((v||"").trim()); }
function buildNearbyForSync(){ var seen={}; var list=[]; function add(uuid,name){ var u=(uuid||"").trim(); if(!isUuid(u)){ return; } if(testActor&&u.toLowerCase()===testActor.toLowerCase()){ return; } var key=u.toLowerCase(); if(seen[key]){ return; } seen[key]=true; list.push({player_uuid:u,name:name||"Nearby"}); } add(peerActor,"Peer Duelist"); add((inputTarget&&inputTarget.value)||"","Selected Target"); add((inputActionTarget&&inputActionTarget.value)||"","Action Target"); return list; }
function postForm(action, payload){ var body=new URLSearchParams(); var merged=withTestParams(Object.assign({action:action}, payload||{})); Object.keys(merged).forEach(function(k){ body.set(k, merged[k]); }); return fetch("/themis/combat_api.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:body.toString()}).then(function(r){return r.json();}); }
function getAction(action, extra){ var qs=new URLSearchParams(withTestParams(Object.assign({action:action}, extra||{}))); return fetch("/themis/combat_api.php?"+qs.toString(),{method:"GET"}).then(function(r){return r.json();}); }
function parseJsonSafe(raw){ if(typeof raw!=="string"||!raw.trim()){ return {}; } try{ var d=JSON.parse(raw); return (d&&typeof d==="object")?d:{}; }catch(_){ return {}; } }
function participantById(participants){ var map={}; (participants||[]).forEach(function(p){ map[String(p.id||"")] = p; }); return map; }
function getRules(instance){ var rules=(instance&&instance.rules&&typeof instance.rules==="object")?instance.rules:{}; var maxPower=Number(rules.max_power||12); var maxStamina=Number(rules.max_stamina||maxPower||12); return { maxPower:Math.max(1,maxPower), maxStamina:Math.max(1,maxStamina) }; }
function getMyParticipant(participants){ var list=Array.isArray(participants)?participants:[]; if(testActor){ var lower=testActor.toLowerCase(); for(var i=0;i<list.length;i++){ var pu=String(list[i].player_uuid||"").toLowerCase(); if(pu===lower){ return list[i]; } } } return list[0]||null; }
function renderVitals(participants){
	var maxRules=getRules(latestInstance||{});
	var rows=(participants||[]).map(function(p){
		var you=(testActor&&p.player_uuid&&String(p.player_uuid).toLowerCase()===testActor.toLowerCase());
		var hp=Number(p.current_hp||0);
		var st=Number(p.current_stamina||0);
		var hpPct=Math.max(0,Math.min(100,(hp/20)*100));
		var stPct=Math.max(0,Math.min(100,(st/maxRules.maxStamina)*100));
		return '<div class="themis-vitals-row"><strong>'+(you?'You':'Opponent')+': '+(p.display_name||p.player_uuid||'Unknown')+'</strong> | HP: <strong>'+hp+'</strong> | Energy: <strong>'+st+'</strong>'+
			'<div class="themis-vitals-bar"><span class="themis-vitals-hp" style="width:'+hpPct+'%"></span></div>'+
			'<div class="themis-vitals-bar"><span class="themis-vitals-energy" style="width:'+stPct+'%"></span></div></div>';
	}).join('');
	var html = rows || '<em>No participant vitals available.</em>';
	if(vitalsBox){ vitalsBox.innerHTML = html; }
	if(vitalsBoxOverview){ vitalsBoxOverview.innerHTML = html; }
}
function renderEnergyTrack(instance){
	var rules=getRules(instance||{});
	var participants=(instance&&Array.isArray(instance.participants))?instance.participants:[];
	var mine=getMyParticipant(participants);
	var stamina=Number((mine&&mine.current_stamina)||0);
	var maxStamina=rules.maxStamina;
	var pct=Math.max(0,Math.min(100,(stamina/maxStamina)*100));
	if(energyFill){ energyFill.style.height=pct+'%'; }
	if(energyText){ energyText.textContent='Energy: '+stamina+'/'+maxStamina; }
	if(inputAttackPower){
		inputAttackPower.max=String(Math.max(0,Math.min(rules.maxPower, stamina)));
		if(Number(inputAttackPower.value||0) > Number(inputAttackPower.max||0)){ inputAttackPower.value=inputAttackPower.max; }
		updatePowerLabel();
	}
}
function eventLine(evt,pMap){ var type=String(evt.event_type||''); var round=evt.round_no?('R'+evt.round_no+': '):''; var payload=parseJsonSafe(evt.event_json||''); if(type==='challenge_created'){ return round+'Challenge issued.'; } if(type==='invite_accepted'){ return round+'Invite accepted.'; } if(type==='invite_declined'){ return round+'Invite declined.'; } if(type==='round_started'){ return round+'Round started.'; } if(type==='round_resolved'){ return round+'Round resolved.'; } if(type==='instance_completed'){ return round+'Combat completed.'; } return round+type.replace(/_/g,' '); }
function renderCombatLog(instance){ if(!combatLog){ return; } var pMap=participantById((instance&&instance.participants)||[]); var events=(instance&&Array.isArray(instance.events))?instance.events:[]; var lines=events.map(function(e){ return eventLine(e,pMap); }); var round=(instance&&instance.round)||null; if(round&&Array.isArray(round.actions)&&round.actions.length){ round.actions.forEach(function(a){ var actor=pMap[String(a.actor_participant_id||'')]||{}; var target=pMap[String(a.target_participant_id||'')]||{}; var who=actor.display_name||actor.player_uuid||'Someone'; var tgt=target.display_name||target.player_uuid||'target'; var action=String(a.action_type||'wait'); var note=String(a.resolution_note||''); var value=Number(a.outcome_value||0); lines.push('R'+round.round_no+': '+who+' used '+action+' on '+tgt+(note?(' ['+note+']'):'')+(value?(' ('+value+')'):'')); }); }
 combatLog.innerHTML = lines.length ? lines.map(function(line){ return '<div style="padding:2px 0;border-bottom:1px dotted #e0d2b6;">'+line+'</div>'; }).join('') : '<em>No combat events yet.</em>'; }
function setSelectedAction(action){ selectedAction=String(action||'attack'); if(selectedActionLabel){ selectedActionLabel.textContent='Selected: '+selectedAction; } actionButtons.forEach(function(btn){ var on=(btn.getAttribute('data-action')===selectedAction); btn.style.opacity=on?'1':'0.7'; btn.style.outline=on?'2px solid #8b6f2e':'none'; }); }
function renderInvitePrompt(){ if(!invitePrompt||!inviteText){ return; } if(!pendingInvites.length){ invitePrompt.style.display="none"; inviteText.textContent=""; return; } var inv=pendingInvites[0]; invitePrompt.style.display="block"; inviteText.textContent="Instance #"+(inv.instance_id||"?")+" from "+(inv.host_display_name||inv.host_player_uuid||"Unknown"); }
function refreshPendingInvites(){
	if(socketConnected()){
		return emitSocket("combat:pending_invites", {}).then(function(reply){
			var data=(reply&&reply.pending)?reply.pending:{};
			pendingInvites=(data&&Array.isArray(data.invites))?data.invites:[];
			renderInvitePrompt();
			return data;
		}).catch(function(){
			pendingInvites=[];
			renderInvitePrompt();
			return {ok:false};
		});
	}
	return getAction("pending_invites").then(function(data){ pendingInvites=(data&&Array.isArray(data.invites))?data.invites:[]; renderInvitePrompt(); return data; }).catch(function(){ pendingInvites=[]; renderInvitePrompt(); return {ok:false}; });
}

function ingestState(data, mirrorOverview){
	if(!(data&&data.instance&&data.instance.id)){ return; }
	latestInstance=data.instance;
	cachedInstanceId=data.instance.id;
	if(inputInstance){ inputInstance.value=String(cachedInstanceId); }
	var participants=(data.instance.participants||[]).filter(function(p){ return p&&p.player_uuid&&p.participant_state==="active"&&p.player_uuid!==testActor; }).map(function(p){ return {player_uuid:p.player_uuid, character_name:(p.display_name||p.player_uuid)}; });
	fillTargetSelects(participants);
	renderVitals(data.instance.participants||[]);
	renderEnergyTrack(data.instance);
	renderCombatLog(data.instance);
	if(mirrorOverview){ setOut(outputOverview,data); }
	setOut(outputInstance,data);
}

function socketConnected(){ return !!(socket&&socket.connected); }

function ensureSocket(){
	if(!realtime||typeof window.io!=="function"||socket){ return; }
	var authPlayer=(realtime.playerUuid||testActor||"").trim();
	if(!authPlayer){ return; }
	var socketOpts={ path:(realtime.path||"/socket.io"), auth:{ playerUuid:authPlayer }, transports:["websocket","polling"] };
	socket=realtime.url?window.io(realtime.url,socketOpts):window.io(socketOpts);
	socket.on("connect", function(){ setOut(outputOverview,{ ok:true, realtime:"connected", socket_id:socket.id }); });
	socket.on("disconnect", function(reason){ setOut(outputOverview,{ ok:false, realtime:"disconnected", reason:reason||"unknown" }); });
	socket.on("connect_error", function(err){ setOut(outputOverview,{ ok:false, realtime:"connect_error", error:(err&&err.message)||"connect failed" }); });
	socket.on("combat:state_updated", function(state){ ingestState(state, true); setOut(outputTurn,{ ok:true, realtime:"state_updated" }); });
	socket.on("combat:invite_created", function(evt){ refreshPendingInvites(); setOut(outputOverview,{ ok:true, realtime:"invite_created", event:evt||{} }); });
	socket.on("combat:invites_updated", function(evt){ pendingInvites=(evt&&Array.isArray(evt.invites))?evt.invites:[]; renderInvitePrompt(); });
	socket.on("combat:invite_responded", function(evt){ refreshPendingInvites(); setOut(outputOverview,{ ok:true, realtime:"invite_responded", event:evt||{} }); });
}

function emitSocket(eventName, payload){
	return new Promise(function(resolve,reject){
		ensureSocket();
		if(!socketConnected()){ reject(new Error("Realtime socket not connected")); return; }
		socket.emit(eventName, payload||{}, function(reply){
			if(!reply||reply.ok===false){ reject(new Error((reply&&reply.error)||("Socket event failed: "+eventName))); return; }
			resolve(reply);
		});
	});
}

document.getElementById("combat_sync_radar")?.addEventListener("click", function(){ var nearby=buildNearbyForSync(); if((inputTarget&&!inputTarget.value)&&peerActor){ inputTarget.value=peerActor; } if((inputActionTarget&&!inputActionTarget.value)&&peerActor){ inputActionTarget.value=peerActor; } var sample=withTestParams({action:"radar_sync",region_name:"combat-test-lab",position:{x:128,y:128,z:20},nearby:nearby}); fetch("/themis/combat_api.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(sample)}).then(function(r){return r.json();}).then(function(data){ setOut(outputOverview,data); }).catch(function(e){ setOut(outputOverview,e.message||"sync error"); }); });

document.getElementById("combat_tick")?.addEventListener("click", function(){ getAction("tick").then(function(data){ setOut(outputOverview,data); }).catch(function(e){ setOut(outputOverview,e.message||"tick error"); }); });

document.getElementById("combat_refresh_state")?.addEventListener("click", function(){
	var instanceId=(cachedInstanceId?Number(cachedInstanceId):Number((inputInstance&&inputInstance.value)||0));
	if(socketConnected()){
		emitSocket("combat:refresh_state", {instanceId:instanceId||undefined}).then(function(reply){ ingestState(reply.state,true); }).catch(function(e){ setOut(outputOverview,e.message||"state error"); });
		return;
	}
	var extra={}; if(cachedInstanceId){ extra.instance_id=String(cachedInstanceId);} getAction("state",extra).then(function(data){ ingestState(data,true); }).catch(function(e){ setOut(outputOverview,e.message||"state error"); });
});

document.getElementById("combat_load_targets")?.addEventListener("click", function(){ getAction("targets").then(function(data){ fillTargetSelects(data&&data.targets?data.targets:[]); setOut(outputTargets,data); }).catch(function(e){ setOut(outputTargets,e.message||"targets error"); }); });
document.getElementById("combat_invite_accept")?.addEventListener("click", function(){
	if(!pendingInvites.length){ setOut(outputOverview,"No pending invite"); return; }
	var inv=pendingInvites[0];
	if(socketConnected()){
		emitSocket("combat:respond_invite", {instanceId:Number(inv.instance_id||0),accept:true}).then(function(reply){
			if(reply&&reply.respond&&reply.respond.instance_id){ cachedInstanceId=reply.respond.instance_id; if(inputInstance){ inputInstance.value=String(cachedInstanceId);} }
			if(reply&&reply.state){ ingestState(reply.state,true); }
			refreshPendingInvites();
			setOut(outputOverview,reply);
		}).catch(function(e){ setOut(outputOverview,e.message||"invite accept error"); });
		return;
	}
	postForm("respond_invite", {instance_id:String(inv.instance_id||0),accept:"1"}).then(function(data){ if(data&&data.instance_id){ cachedInstanceId=data.instance_id; if(inputInstance){ inputInstance.value=String(cachedInstanceId);} } refreshPendingInvites(); setOut(outputOverview,data); }).catch(function(e){ setOut(outputOverview,e.message||"invite accept error"); });
});

document.getElementById("combat_invite_decline")?.addEventListener("click", function(){
	if(!pendingInvites.length){ setOut(outputOverview,"No pending invite"); return; }
	var inv=pendingInvites[0];
	if(socketConnected()){
		emitSocket("combat:respond_invite", {instanceId:Number(inv.instance_id||0),accept:false}).then(function(reply){ refreshPendingInvites(); setOut(outputOverview,reply); }).catch(function(e){ setOut(outputOverview,e.message||"invite decline error"); });
		return;
	}
	postForm("respond_invite", {instance_id:String(inv.instance_id||0),accept:"0"}).then(function(data){ refreshPendingInvites(); setOut(outputOverview,data); }).catch(function(e){ setOut(outputOverview,e.message||"invite decline error"); });
});
inputTargetSelect?.addEventListener("change", function(){ if(inputTarget){ inputTarget.value=(inputTargetSelect&&inputTargetSelect.value)||""; } if(inputActionTarget && !inputActionTarget.value){ inputActionTarget.value=(inputTargetSelect&&inputTargetSelect.value)||""; } if(inputActionTargetSelect){ inputActionTargetSelect.value=(inputTargetSelect&&inputTargetSelect.value)||""; } });
inputActionTargetSelect?.addEventListener("change", function(){ if(inputActionTarget){ inputActionTarget.value=(inputActionTargetSelect&&inputActionTargetSelect.value)||""; } });
inputAttackPower?.addEventListener("input", updatePowerLabel);
actionButtons.forEach(function(btn){ btn.addEventListener("click", function(){ setSelectedAction(btn.getAttribute("data-action")||"attack"); }); });

document.getElementById("combat_challenge")?.addEventListener("click", function(){
	var t=(inputTarget&&inputTarget.value||"").trim(); if(!t){ setOut(outputTargets,"target uuid required"); return;}
	if(socketConnected()){
		emitSocket("combat:challenge", {targetPlayerUuid:t,turnSeconds:90}).then(function(reply){
			if(reply&&reply.challenge&&reply.challenge.instance_id){ cachedInstanceId=reply.challenge.instance_id; if(inputInstance){inputInstance.value=String(cachedInstanceId);} }
			if(reply&&reply.state){ ingestState(reply.state,true); }
			setOut(outputTargets,reply);
		}).catch(function(e){ setOut(outputTargets,e.message||"challenge error"); });
		return;
	}
	postForm("challenge", {target_player_uuid:t,turn_seconds:"90"}).then(function(data){
		if(data&&data.instance_id){ cachedInstanceId=data.instance_id; if(inputInstance){inputInstance.value=String(cachedInstanceId);} if(socketConnected()){ emitSocket("combat:join_instance", {instanceId:Number(cachedInstanceId)}).then(function(reply){ ingestState(reply.state,true); }).catch(function(){}); } }
		setOut(outputTargets,data);
	}).catch(function(e){ setOut(outputTargets,e.message||"challenge error"); });
});

document.getElementById("combat_join_host")?.addEventListener("click", function(){
	var host=(inputTarget&&inputTarget.value||"").trim();
	postForm("join", {host_player_uuid:host}).then(function(data){
		if(data&&data.instance_id){ cachedInstanceId=data.instance_id; if(inputInstance){inputInstance.value=String(cachedInstanceId);} if(socketConnected()){ emitSocket("combat:join_instance", {instanceId:Number(cachedInstanceId)}).then(function(reply){ ingestState(reply.state,true); }).catch(function(){}); } }
		setOut(outputTargets,data);
	}).catch(function(e){ setOut(outputTargets,e.message||"join error"); });
});

document.getElementById("combat_load_instance")?.addEventListener("click", function(){
	var id=(inputInstance&&inputInstance.value||"").trim();
	if(id&&socketConnected()){
		cachedInstanceId=Number(id);
		emitSocket("combat:join_instance", {instanceId:Number(id)}).then(function(reply){ ingestState(reply.state,true); }).catch(function(e){ setOut(outputInstance,e.message||"instance error"); });
		return;
	}
	var extra={}; if(id){ extra.instance_id=id; cachedInstanceId=Number(id);} getAction("state",extra).then(function(data){ ingestState(data,false); }).catch(function(e){ setOut(outputInstance,e.message||"instance error"); });
});

document.getElementById("combat_submit_action")?.addEventListener("click", function(){
	var id=(inputInstance&&inputInstance.value||"").trim(); if(!id&&cachedInstanceId){ id=String(cachedInstanceId);} if(!id){ setOut(outputTurn,"instance id required"); return;}
	var actionType=(selectedAction||"wait");
	var targetUuid=(inputActionTarget&&inputActionTarget.value||"").trim();
	var payloadObj={ attack_kind:(inputAttackKind&&inputAttackKind.value||"physical"), power:Number(inputAttackPower&&inputAttackPower.value||"6"), lock_in:true };
	var payloadRaw=(inputPayload&&inputPayload.value||"").trim();
	if(payloadRaw!==""){ try{ var parsed=JSON.parse(payloadRaw); if(parsed&&typeof parsed==="object"){ Object.keys(parsed).forEach(function(k){ payloadObj[k]=parsed[k]; }); } } catch(e){ setOut(outputTurn,"payload must be valid JSON"); return; } }

	if(socketConnected()){
		emitSocket("combat:submit_action", {instanceId:Number(id), actionType:actionType, targetPlayerUuid:targetUuid, payload:payloadObj})
			.then(function(reply){ if(reply&&reply.state){ ingestState(reply.state,true); } setOut(outputTurn,reply); })
			.catch(function(e){ setOut(outputTurn,e.message||"submit error"); });
		return;
	}

	postForm("submit_action", {instance_id:id,action_type:actionType,target_player_uuid:targetUuid,payload:JSON.stringify(payloadObj)})
		.then(function(data){ setOut(outputTurn,data); })
		.catch(function(e){ setOut(outputTurn,e.message||"submit error"); });
});

updatePowerLabel();
setSelectedAction(selectedAction);
renderVitals([]);
renderEnergyTrack(null);
ensureSocket();
setTimeout(ensureSocket, 400);
setTimeout(ensureSocket, 1200);
refreshPendingInvites();
setInterval(refreshPendingInvites, 4000);
})();
</script>
HTML;

echo $out;
