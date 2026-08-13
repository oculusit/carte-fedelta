/*
 * FidAPPti - Loyalty Card Manager
 * Copyright (c) 2025 Alessandro "OCULUS" Blasi
 * https://www.oculus.it
 *
 * Licensed under the MIT License.
 * See LICENSE file in the project root for details.
 */

package it.oculus.carte;

import android.app.Activity;
import android.app.PendingIntent;
import android.content.ComponentName;
import android.content.Intent;
import android.content.IntentFilter;
import android.nfc.NdefMessage;
import android.nfc.NdefRecord;
import android.nfc.NfcAdapter;
import android.nfc.cardemulation.CardEmulation;
import android.util.Log;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.nio.charset.StandardCharsets;

@CapacitorPlugin(name = "NdefEmulation")
public class NdefEmulationPlugin extends Plugin {

    private static final String TAG = "NdefEmulation";

    private boolean foregroundDispatchEnabled = false;

    @PluginMethod
    public void start(PluginCall call) {
        String vcard = call.getString("vcard", "");
        if (vcard == null || vcard.isEmpty()) {
            call.reject("vcard è obbligatorio");
            return;
        }
        try {
            NdefRecord record = NdefRecord.createMime("text/x-vCard", vcard.getBytes(StandardCharsets.UTF_8));
            NdefMessage ndef = new NdefMessage(new NdefRecord[]{ record });
            NdefTagService.setMessage(ndef);
            activateEmulation();
            JSObject ret = new JSObject();
            ret.put("active", NdefTagService.isMessageSet());
            ret.put("size", ndef.toByteArray().length);
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "start error", e);
            call.reject("Impossibile attivare la condivisione NFC: " + e.getMessage());
        }
    }

    @PluginMethod
    public void stop(PluginCall call) {
        releaseEmulation();
        NdefTagService.clearMessage();
        JSObject ret = new JSObject();
        ret.put("active", NdefTagService.isMessageSet());
        call.resolve(ret);
    }

    @Override
    protected void handleOnResume() {
        super.handleOnResume();
        if (NdefTagService.isMessageSet()) {
            activateEmulation();
        }
    }

    @Override
    protected void handleOnPause() {
        super.handleOnPause();
        releaseEmulation();
    }

    @Override
    protected void handleOnDestroy() {
        super.handleOnDestroy();
        releaseEmulation();
        NdefTagService.clearMessage();
    }

    private void activateEmulation() {
        Activity activity = getActivity();
        if (activity == null) return;
        NfcAdapter adapter = NfcAdapter.getDefaultAdapter(getContext());
        if (adapter == null) return;

        // Route HCE to our service while the activity is in the foreground.
        try {
            CardEmulation cardEmulation = CardEmulation.getInstance(adapter);
            if (cardEmulation != null) {
                cardEmulation.setPreferredService(activity, new ComponentName(getContext(), NdefTagService.class));
            }
        } catch (Exception e) {
            Log.w(TAG, "setPreferredService failed", e);
        }

        // Swallow tag discoveries so the OS does not show the "open with"
        // chooser on the server phone while it is emitting.
        try {
            if (foregroundDispatchEnabled) return;
            Intent intent = new Intent(activity, activity.getClass())
                    .addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP);
            PendingIntent pendingIntent = PendingIntent.getActivity(
                    activity, 0, intent,
                    PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_MUTABLE);
            IntentFilter filter = new IntentFilter();
            filter.addAction(NfcAdapter.ACTION_TAG_DISCOVERED);
            adapter.enableForegroundDispatch(activity, pendingIntent, new IntentFilter[]{ filter }, null);
            foregroundDispatchEnabled = true;
            Log.d(TAG, "foreground dispatch enabled (tag reader swallowed)");
        } catch (Exception e) {
            Log.w(TAG, "enableForegroundDispatch failed", e);
        }
    }

    private void releaseEmulation() {
        Activity activity = getActivity();
        if (activity == null) return;
        NfcAdapter adapter = NfcAdapter.getDefaultAdapter(getContext());
        if (adapter == null) return;
        try {
            if (foregroundDispatchEnabled) {
                adapter.disableForegroundDispatch(activity);
                foregroundDispatchEnabled = false;
                Log.d(TAG, "foreground dispatch disabled");
            }
            CardEmulation cardEmulation = CardEmulation.getInstance(adapter);
            if (cardEmulation != null) {
                cardEmulation.unsetPreferredService(activity);
            }
        } catch (Exception e) {
            Log.w(TAG, "releaseEmulation failed", e);
        }
    }

    @PluginMethod
    public void isActive(PluginCall call) {
        JSObject ret = new JSObject();
        ret.put("active", NdefTagService.isMessageSet());
        call.resolve(ret);
    }
}
