/*
 * FidAPPti - Loyalty Card Manager
 * Copyright (c) 2025 Alessandro "OCULUS" Blasi
 * https://www.oculus.it
 *
 * Licensed under the MIT License.
 * See LICENSE file in the project root for details.
 */

package it.oculus.carte;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(FilePickerPlugin.class);
        super.onCreate(savedInstanceState);
    }
}
