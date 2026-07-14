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

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.OutputStream;

@CapacitorPlugin(name = "FilePicker")
public class FilePickerPlugin extends Plugin {

    @PluginMethod
    public void saveFile(PluginCall call) {
        String filename = call.getString("filename", "backup.json");
        String data = call.getString("data", "");
        String mimeType = call.getString("mimeType", "application/json");

        try {
            // Write data to a temp file in app cache
            File tempFile = new File(getContext().getCacheDir(), "export_temp.json");
            FileOutputStream fos = new FileOutputStream(tempFile);
            fos.write(data.getBytes("UTF-8"));
            fos.flush();
            fos.close();

            // Store filename and mime in plugin ref for callback
            getBridge().saveCall(call);

            Intent intent = new Intent(Intent.ACTION_CREATE_DOCUMENT);
            intent.addCategory(Intent.CATEGORY_OPENABLE);
            intent.setType(mimeType);
            intent.putExtra(Intent.EXTRA_TITLE, filename);

            startActivityForResult(call, intent, "handleSaveResult");
        } catch (Exception e) {
            call.reject("Errore preparazione: " + e.getMessage());
        }
    }

    @ActivityCallback
    private void handleSaveResult(PluginCall call, Intent intent) {
        if (intent == null || intent.getData() == null) {
            call.reject("Salvataggio annullato");
            return;
        }

        Uri uri = intent.getData();

        try {
            // Read from temp file
            File tempFile = new File(getContext().getCacheDir(), "export_temp.json");
            InputStream is = new FileInputStream(tempFile);

            // Write to user-selected URI
            OutputStream os = getContext().getContentResolver().openOutputStream(uri);
            if (os != null) {
                byte[] buffer = new byte[8192];
                int bytesRead;
                while ((bytesRead = is.read(buffer)) != -1) {
                    os.write(buffer, 0, bytesRead);
                }
                os.flush();
                os.close();
            }
            is.close();

            // Get display name
            String displayName = "";
            Cursor cursor = getContext().getContentResolver().query(uri, null, null, null, null);
            if (cursor != null) {
                int nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME);
                if (cursor.moveToFirst() && nameIndex >= 0) {
                    displayName = cursor.getString(nameIndex);
                }
                cursor.close();
            }

            // Clean up temp file
            tempFile.delete();

            JSObject result = new JSObject();
            result.put("uri", uri.toString());
            result.put("filename", displayName);
            call.resolve(result);
        } catch (Exception e) {
            call.reject("Errore scrittura: " + e.getMessage());
        }
    }
}
