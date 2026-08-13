/*
 * FidAPPti - Loyalty Card Manager
 * Copyright (c) 2025 Alessandro "OCULUS" Blasi
 * https://www.oculus.it
 *
 * Licensed under the MIT License.
 * See LICENSE file in the project root for details.
 */

package it.oculus.carte;

import android.nfc.NdefMessage;
import android.nfc.NdefRecord;
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

    @PluginMethod
    public void start(PluginCall call) {
        String vcard = call.getString("vcard", "");
        if (vcard == null || vcard.isEmpty()) {
            call.reject("vcard è obbligatorio");
            return;
        }
        try {
            NdefRecord record = NdefRecord.createMime("text/x-vcard", vcard.getBytes(StandardCharsets.UTF_8));
            NdefMessage ndef = new NdefMessage(new NdefRecord[]{ record });
            NdefTagService.setMessage(ndef);
            JSObject ret = new JSObject();
            ret.put("active", NdefTagService.isMessageSet());
            call.resolve(ret);
        } catch (Exception e) {
            Log.e(TAG, "start error", e);
            call.reject("Impossibile attivare la condivisione NFC: " + e.getMessage());
        }
    }

    @PluginMethod
    public void stop(PluginCall call) {
        NdefTagService.clearMessage();
        JSObject ret = new JSObject();
        ret.put("active", NdefTagService.isMessageSet());
        call.resolve(ret);
    }

    @PluginMethod
    public void isActive(PluginCall call) {
        JSObject ret = new JSObject();
        ret.put("active", NdefTagService.isMessageSet());
        call.resolve(ret);
    }
}
