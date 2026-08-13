/*
 * FidAPPti - Loyalty Card Manager
 * Copyright (c) 2025 Alessandro "OCULUS" Blasi
 * https://www.oculus.it
 *
 * Licensed under the MIT License.
 * See LICENSE file in the project root for details.
 *
 * HCE Type 4 Tag emulation: exposes the current vCard as an NDEF
 * Type 4 Tag so that a nearby NFC device reads it like a real tag.
 */

package it.oculus.carte;

import android.nfc.NdefMessage;
import android.nfc.cardemulation.HostApduService;
import android.os.Bundle;
import android.util.Log;

import java.util.Arrays;

public class NdefTagService extends HostApduService {

    private static final String TAG = "NdefTagService";

    // NDEF Tag Application AID (Type 4 Tag)
    private static final byte[] NDEF_APPLICATION_AID =
        { (byte) 0xD2, 0x76, 0x00, 0x00, (byte) 0x85, 0x01, 0x01 };

    private static final byte[] SW_SUCCESS = { (byte) 0x90, 0x00 };
    private static final byte[] SW_FILE_NOT_FOUND = { (byte) 0x6A, (byte) 0x82 };
    private static final byte[] SW_FUNC_NOT_SUPPORTED = { (byte) 0x6A, (byte) 0x86 };
    private static final byte[] SW_WRONG_PARAMS = { (byte) 0x6A, (byte) 0x00 };
    private static final byte[] SW_WRONG_CLA = { (byte) 0x6E, 0x00 };

    private static final byte[] CC_FILE_ID = { (byte) 0xE1, (byte) 0x03 };
    private static final byte[] NDEF_FILE_ID = { (byte) 0xE1, (byte) 0x04 };

    private static final int MLe = 0xFF;
    private static final int MLc = 0x40;

    // Capability Container (15 bytes, Type 4 Tag v2.0). Built dynamically so
    // the declared Maximum NDEF file size matches the current message and MLe
    // is large enough that readers can fetch a big vCard in one chunk.
    private static byte[] buildCapabilityContainer(int ndefFileSize) {
        return new byte[] {
            (byte) 0x00, 0x0F,                       // CCLEN = 15
            0x20,                                    // Mapping version 2.0
            (byte) 0x00, (byte) MLe,                 // MLe (max read length)
            (byte) 0x00, (byte) MLc,                 // MLc (max write length)
            0x04, 0x06,                              // NDEF File Control TLV
            (byte) 0xE1, 0x04,                       // NDEF file identifier
            (byte) ((ndefFileSize >> 8) & 0xFF), (byte) (ndefFileSize & 0xFF),
            0x00,                                    // Read access: granted
            (byte) 0xFF                              // Write access: denied
        };
    }

    private static final int FILE_APP = 0;
    private static final int FILE_CC = 1;
    private static final int FILE_NDEF = 2;

    private static volatile NdefMessage message = null;
    private static volatile byte[] capabilityContainer = null;
    private static volatile byte[] ndefFile = null;

    private int selectedFile = FILE_APP;

    // NFC Forum Type 4 Tag NDEF file: 2-byte big-endian NLEN (message
    // length) followed by the NDEF message. (The 0x03 <len> <msg> 0xFE TLV
    // structure belongs to Type 2 tags, not Type 4.)
    private static byte[] buildNdefFile(byte[] ndefBytes) {
        byte[] file = new byte[ndefBytes.length + 2];
        file[0] = (byte) ((ndefBytes.length >> 8) & 0xFF);
        file[1] = (byte) (ndefBytes.length & 0xFF);
        System.arraycopy(ndefBytes, 0, file, 2, ndefBytes.length);
        return file;
    }

    public static void setMessage(NdefMessage m) {
        message = m;
        if (m != null) {
            byte[] ndef = m.toByteArray();
            ndefFile = buildNdefFile(ndef);
            capabilityContainer = buildCapabilityContainer(ndefFile.length);
            Log.d(TAG, "setMessage: " + ndef.length + " bytes, NDEF file=" + ndefFile.length + " (NLEN), CC max=" + ndefFile.length);
        } else {
            capabilityContainer = null;
            ndefFile = null;
            Log.d(TAG, "setMessage: null");
        }
    }

    public static void clearMessage() {
        message = null;
        ndefFile = null;
        Log.d(TAG, "clearMessage");
    }

    public static boolean isMessageSet() {
        return message != null;
    }

    @Override
    public byte[] processCommandApdu(byte[] commandApdu, Bundle extras) {
        if (commandApdu == null || commandApdu.length < 4) {
            return SW_WRONG_PARAMS;
        }

        byte cla = commandApdu[0];
        byte ins = commandApdu[1];
        byte p1 = commandApdu[2];
        byte p2 = commandApdu[3];

        if (cla != 0x00) {
            return SW_WRONG_CLA;
        }

        if (ins == (byte) 0xA4) {
            // SELECT
            if (commandApdu.length < 5) return SW_WRONG_PARAMS;
            byte[] cmd = commandApdu;
            if (contains(cmd, NDEF_APPLICATION_AID)) {
                selectedFile = FILE_APP;
                Log.d(TAG, "SELECT AID -> 9000 cmd=" + hex(cmd));
                return SW_SUCCESS;
            }
            if (contains(cmd, CC_FILE_ID)) {
                selectedFile = FILE_CC;
                Log.d(TAG, "SELECT CC -> 9000 cmd=" + hex(cmd));
                return SW_SUCCESS;
            }
            if (contains(cmd, NDEF_FILE_ID)) {
                selectedFile = FILE_NDEF;
                Log.d(TAG, "SELECT NDEF -> 9000 cmd=" + hex(cmd));
                return SW_SUCCESS;
            }
            Log.d(TAG, "SELECT unknown -> 6A82 cmd=" + hex(cmd));
            return SW_FILE_NOT_FOUND;
        }

        if (ins == (byte) 0xB0) {
            // READ BINARY
            int fileOffset = ((p1 & 0xFF) << 8) | (p2 & 0xFF);
            int le = commandApdu.length > 5 ? (commandApdu[5] & 0xFF) : 0;
            if (le == 0) le = MLe;

            byte[] fileData = null;
            switch (selectedFile) {
                case FILE_CC:
                    fileData = capabilityContainer;
                    break;
                case FILE_NDEF:
                    fileData = ndefFile;
                    break;
                default:
                    return SW_FILE_NOT_FOUND;
            }
            if (fileData == null) return SW_FILE_NOT_FOUND;

            if (fileOffset > fileData.length) {
                return SW_WRONG_PARAMS;
            }
            int end = Math.min(fileOffset + le, fileData.length);
            byte[] response = Arrays.copyOfRange(fileData, fileOffset, end);
            Log.d(TAG, "READ offset=" + fileOffset + " le=" + le + " resp=" + response.length + "/" + fileData.length);
            return concat(response, SW_SUCCESS);
        }

        return SW_FUNC_NOT_SUPPORTED;
    }

    @Override
    public void onDeactivated(int reason) {
        selectedFile = FILE_APP;
    }

    private static byte[] concat(byte[] a, byte[] b) {
        byte[] out = new byte[a.length + b.length];
        System.arraycopy(a, 0, out, 0, a.length);
        System.arraycopy(b, 0, out, a.length, b.length);
        return out;
    }

    private static boolean contains(byte[] haystack, byte[] needle) {
        if (needle.length == 0 || haystack.length < needle.length) {
            return false;
        }
        for (int i = 0; i <= haystack.length - needle.length; i++) {
            boolean match = true;
            for (int j = 0; j < needle.length; j++) {
                if (haystack[i + j] != needle[j]) {
                    match = false;
                    break;
                }
            }
            if (match) {
                return true;
            }
        }
        return false;
    }

    private static String hex(byte[] data) {
        StringBuilder sb = new StringBuilder(data.length * 2);
        for (byte b : data) {
            sb.append(String.format("%02X", b & 0xFF));
        }
        return sb.toString();
    }
}
