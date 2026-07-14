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
