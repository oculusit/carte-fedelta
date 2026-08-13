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

import java.util.Arrays;

public class NdefTagService extends HostApduService {

    private static final String TAG = "NdefTagService";

    // NDEF Tag Application AID (Type 4 Tag)
    private static final byte[] NDEF_APPLICATION_AID =
        { (byte) 0xD2, 0x76, 0x00, 0x00, 0x85, 0x01, 0x01 };

    private static final byte[] SW_SUCCESS = { (byte) 0x90, 0x00 };
    private static final byte[] SW_FILE_NOT_FOUND = { (byte) 0x6A, (byte) 0x82 };
    private static final byte[] SW_FUNC_NOT_SUPPORTED = { (byte) 0x6A, (byte) 0x86 };
    private static final byte[] SW_WRONG_PARAMS = { (byte) 0x6A, (byte) 0x00 };
    private static final byte[] SW_WRONG_CLA = { (byte) 0x6E, 0x00 };

    private static final byte[] CC_FILE_ID = { (byte) 0xE1, (byte) 0x03 };
    private static final byte[] NDEF_FILE_ID = { (byte) 0xE1, (byte) 0x04 };

    private static final int MLe = 0x3B;

    // Capability Container (15 bytes, Type 4 Tag v2.0)
    private static final byte[] CAPABILITY_CONTAINER = {
        (byte) 0x00, 0x0F,        // CCLEN = 15
        0x20,                     // Mapping version 2.0
        0x00, MLe,                // MLe (max read length)
        0x00, 0x34,               // MLc (max write length)
        0x04, 0x06,               // NDEF File Control TLV
        (byte) 0xE1, 0x04,        // NDEF file identifier
        (byte) 0xFF, (byte) 0xFE, // Max NDEF size
        0x00,                     // Read access: granted
        (byte) 0xFF               // Write access: denied
    };

    private static final int FILE_APP = 0;
    private static final int FILE_CC = 1;
    private static final int FILE_NDEF = 2;

    private static volatile NdefMessage message = null;

    private int selectedFile = FILE_APP;

    public static void setMessage(NdefMessage m) {
        message = m;
    }

    public static void clearMessage() {
        message = null;
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
            if (commandApdu.length < 7) return SW_WRONG_PARAMS;
            byte[] data = Arrays.copyOfRange(commandApdu, 5, commandApdu.length);
            if (Arrays.equals(data, NDEF_APPLICATION_AID)) {
                selectedFile = FILE_APP;
                return SW_SUCCESS;
            }
            if (Arrays.equals(data, CC_FILE_ID)) {
                selectedFile = FILE_CC;
                return SW_SUCCESS;
            }
            if (Arrays.equals(data, NDEF_FILE_ID)) {
                selectedFile = FILE_NDEF;
                return SW_SUCCESS;
            }
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
                    fileData = CAPABILITY_CONTAINER;
                    break;
                case FILE_NDEF: {
                    NdefMessage m = message;
                    if (m == null) return SW_FILE_NOT_FOUND;
                    byte[] ndefBytes = m.toByteArray();
                    fileData = new byte[ndefBytes.length + 2];
                    fileData[0] = (byte) ((ndefBytes.length >> 8) & 0xFF);
                    fileData[1] = (byte) (ndefBytes.length & 0xFF);
                    System.arraycopy(ndefBytes, 0, fileData, 2, ndefBytes.length);
                    break;
                }
                default:
                    return SW_FILE_NOT_FOUND;
            }

            if (fileOffset > fileData.length) {
                return SW_WRONG_PARAMS;
            }
            int end = Math.min(fileOffset + le, fileData.length);
            byte[] response = Arrays.copyOfRange(fileData, fileOffset, end);
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
}
