package it.oculus.carte;

import android.content.ContentValues;
import android.content.ContentResolver;
import android.net.Uri;
import android.os.Environment;
import android.provider.MediaStore;
import android.util.Log;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.io.File;
import java.io.OutputStream;

@CapacitorPlugin(name = "FilePicker")
public class FilePickerPlugin extends Plugin {

    private static final String TAG = "FilePicker";

    @PluginMethod
    public void saveToDownloads(PluginCall call) {
        String filename = call.getString("filename", "backup.json");
        String data = call.getString("data", "");

        Log.d(TAG, "saveToDownloads: filename=" + filename + " dataLen=" + data.length());

        try {
            ContentResolver resolver = getContext().getContentResolver();

            ContentValues values = new ContentValues();
            values.put(MediaStore.Downloads.DISPLAY_NAME, filename);
            values.put(MediaStore.Downloads.MIME_TYPE, "application/json");
            values.put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS);

            Uri uri = resolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values);

            if (uri == null) {
                Log.e(TAG, "MediaStore insert returned null");
                call.reject("Impossibile creare il file nella cartella Download");
                return;
            }

            Log.d(TAG, "MediaStore URI: " + uri);

            OutputStream os = resolver.openOutputStream(uri, "wt");
            if (os == null) {
                call.reject("Impossibile aprire lo stream di scrittura");
                return;
            }

            byte[] bytes = data.getBytes("UTF-8");
            os.write(bytes);
            os.flush();
            os.close();

            Log.d(TAG, "Written " + bytes.length + " bytes to Downloads");

            String downloadPath = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS) + "/" + filename;

            JSObject result = new JSObject();
            result.put("uri", uri.toString());
            result.put("path", downloadPath);
            result.put("filename", filename);
            result.put("size", bytes.length);
            call.resolve(result);

        } catch (Exception e) {
            Log.e(TAG, "saveToDownloads error", e);
            call.reject("Errore salvataggio: " + e.getMessage());
        }
    }
}
