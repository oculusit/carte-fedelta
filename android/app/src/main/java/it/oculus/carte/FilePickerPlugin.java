package it.oculus.carte;

import android.content.Intent;
import android.database.Cursor;
import android.net.Uri;
import android.provider.OpenableColumns;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.ActivityCallback;

import java.io.OutputStream;

@CapacitorPlugin(name = "FilePicker")
public class FilePickerPlugin extends Plugin {

    private String pendingData = "";

    @PluginMethod
    public void saveFile(PluginCall call) {
        String filename = call.getString("filename", "backup.json");
        String data = call.getString("data", "");
        String mimeType = call.getString("mimeType", "application/json");

        this.pendingData = data;

        Intent intent = new Intent(Intent.ACTION_CREATE_DOCUMENT);
        intent.addCategory(Intent.CATEGORY_OPENABLE);
        intent.setType(mimeType);
        intent.putExtra(Intent.EXTRA_TITLE, filename);

        startActivityForResult(call, intent, "handleSaveResult");
    }

    @ActivityCallback
    private void handleSaveResult(PluginCall call, Intent intent) {
        if (intent == null || intent.getData() == null) {
            call.reject("Salvataggio annullato");
            return;
        }

        Uri uri = intent.getData();

        try {
            OutputStream os = getContext().getContentResolver().openOutputStream(uri);
            if (os != null) {
                os.write(pendingData.getBytes("UTF-8"));
                os.flush();
                os.close();
            }

            String displayName = "";
            Cursor cursor = getContext().getContentResolver().query(uri, null, null, null, null);
            if (cursor != null) {
                int nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME);
                if (cursor.moveToFirst() && nameIndex >= 0) {
                    displayName = cursor.getString(nameIndex);
                }
                cursor.close();
            }

            JSObject result = new JSObject();
            result.put("uri", uri.toString());
            result.put("filename", displayName);
            call.resolve(result);
        } catch (Exception e) {
            call.reject("Errore scrittura: " + e.getMessage());
        }
    }
}
